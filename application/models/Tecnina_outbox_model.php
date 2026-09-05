<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_outbox_model extends CI_Model
{
    const STATE_PENDING = 'PENDING';

    const STATE_CLAIMED = 'CLAIMED';

    const STATE_ACKNOWLEDGED = 'ACKNOWLEDGED';

    public function claim($batchSize, $claimTtlSeconds)
    {
        $batchSize = max(1, min(100, (int) $batchSize));
        $claimTtlSeconds = max(30, min(900, (int) $claimTtlSeconds));
        $table = $this->quotedTable();
        $claimToken = $this->uuidV4();

        $this->db->trans_begin();
        try {
            $rows = $this->db->query(
                "SELECT `id` FROM {$table} "
                . "WHERE `state` = 'PENDING' "
                . "OR (`state` = 'CLAIMED' AND `claim_expires_at` <= UTC_TIMESTAMP()) "
                . "ORDER BY `id` ASC LIMIT {$batchSize} FOR UPDATE"
            )->result_array();

            if ($rows === []) {
                $this->db->trans_commit();

                return ['claim_token' => null, 'events' => []];
            }

            $ids = array_map('intval', array_column($rows, 'id'));
            $idList = implode(',', $ids);
            $this->db->query(
                "UPDATE {$table} SET `state` = 'CLAIMED', `claimed_at` = UTC_TIMESTAMP(), "
                . '`claim_token` = ?, `claim_expires_at` = DATE_ADD(UTC_TIMESTAMP(), INTERVAL '
                . $claimTtlSeconds . ' SECOND), `attempts` = `attempts` + 1 '
                . "WHERE `id` IN ({$idList})",
                [$claimToken]
            );

            $events = $this->db->query(
                'SELECT `event_id`, `event_type`, `os_id`, `client_id`, '
                . '`old_status`, `new_status`, `created_at` '
                . "FROM {$table} WHERE `id` IN ({$idList}) ORDER BY `id` ASC"
            )->result_array();

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('claim transaction failed');
            }
            $this->db->trans_commit();

            return ['claim_token' => $claimToken, 'events' => $events];
        } catch (Throwable $exception) {
            $this->db->trans_rollback();
            throw $exception;
        }
    }

    public function acknowledge($claimToken, array $eventIds)
    {
        $table = $this->quotedTable();
        $eventIds = array_values(array_unique($eventIds));
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));

        $this->db->trans_begin();
        try {
            $parameters = array_merge([$claimToken], $eventIds);
            $rows = $this->db->query(
                'SELECT `event_id`, `state` FROM ' . $table
                . ' WHERE `claim_token` = ? AND `event_id` IN (' . $placeholders . ') FOR UPDATE',
                $parameters
            )->result_array();

            if (count($rows) !== count($eventIds)) {
                $this->db->trans_rollback();

                return false;
            }

            foreach ($rows as $row) {
                if (! in_array($row['state'], [self::STATE_CLAIMED, self::STATE_ACKNOWLEDGED], true)) {
                    $this->db->trans_rollback();

                    return false;
                }
            }

            $this->db->query(
                'UPDATE ' . $table . " SET `state` = 'ACKNOWLEDGED', "
                . '`acknowledged_at` = COALESCE(`acknowledged_at`, UTC_TIMESTAMP()) '
                . 'WHERE `claim_token` = ? AND `event_id` IN (' . $placeholders . ') '
                . "AND `state` = 'CLAIMED'",
                $parameters
            );

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('ack transaction failed');
            }
            $this->db->trans_commit();

            return true;
        } catch (Throwable $exception) {
            $this->db->trans_rollback();
            throw $exception;
        }
    }

    private function quotedTable()
    {
        $physical = $this->db->dbprefix('tecnina_integration_outbox');
        if (! preg_match('/^[A-Za-z0-9_]+$/', $physical)) {
            throw new RuntimeException('invalid database prefix');
        }

        return '`' . $physical . '`';
    }

    private function uuidV4()
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-'
            . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-'
            . substr($hex, 20, 12);
    }
}
