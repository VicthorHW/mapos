<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_legal_model extends CI_Model
{
    public function getCurrentVersions(?string $asOf = null): array
    {
        $targetTime = $asOf !== null ? $this->normalizeUtcTime($asOf) : gmdate('Y-m-d H:i:s');
        if ($targetTime === null) {
            return ['ok' => false, 'reason' => 'invalid_as_of_time'];
        }

        $versions = [];
        foreach (['TERMS_OF_USE', 'PRIVACY_POLICY'] as $docType) {
            $query = $this->db
                ->select('id, document_type, version, canonical_content, content_hash, public_url, status, effective_at, published_at, requires_new_acceptance, communication_required, re_manifestation_reason')
                ->from('tecnina_legal_document_versions')
                ->where('document_type', $docType)
                ->where('status', 'PUBLISHED')
                ->where('effective_at <=', $targetTime)
                ->order_by('effective_at', 'DESC')
                ->order_by('id', 'DESC')
                ->limit(2)
                ->get();

            $rows = $query->result_array();
            if (count($rows) === 0) {
                return ['ok' => false, 'reason' => 'legal_catalog_inconsistent'];
            }

            $current = $rows[0];
            $requiredAction = $docType === 'TERMS_OF_USE' ? 'ACCEPTED' : 'ACKNOWLEDGED';

            $versions[$docType] = [
                'document_version_id' => (int) $current['id'],
                'document_type' => $current['document_type'],
                'version' => $current['version'],
                'content_hash' => $current['content_hash'],
                'public_url' => $current['public_url'],
                'effective_at' => $current['effective_at'],
                'required_action' => $requiredAction,
                'requires_new_acceptance' => (bool) $current['requires_new_acceptance'],
                'communication_required' => (bool) $current['communication_required'],
            ];
        }

        return ['ok' => true, 'versions' => $versions];
    }

    public function getVersionById(int $id): ?array
    {
        $row = $this->db
            ->select('id, document_type, version, canonical_content, content_hash, public_url, status, effective_at, published_at, requires_new_acceptance, communication_required, re_manifestation_reason')
            ->from('tecnina_legal_document_versions')
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    public function recordManifestations(array $payload): array
    {
        $intakeId = !empty($payload['subject']['intake_id']) ? trim((string) $payload['subject']['intake_id']) : null;
        $clientId = !empty($payload['subject']['client_id']) ? (int) $payload['subject']['client_id'] : null;

        if ($intakeId === null && $clientId === null) {
            return ['ok' => false, 'reason' => 'missing_subject'];
        }

        $idempotencyKey = trim((string) ($payload['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            return ['ok' => false, 'reason' => 'missing_idempotency_key'];
        }

        $items = $payload['manifestations'] ?? null;
        if (!is_array($items) || count($items) === 0) {
            return ['ok' => false, 'reason' => 'missing_manifestations'];
        }

        // Check idempotency replay
        $existingRows = $this->db
            ->select('event_id, idempotency_key, document_version_id, intake_id, client_id_at_event, action, occurred_at, document_type_snapshot, document_version_snapshot, document_hash_snapshot')
            ->from('tecnina_legal_acceptances')
            ->group_start()
                ->where('idempotency_key', $idempotencyKey)
                ->or_like('idempotency_key', $idempotencyKey . ':', 'after')
            ->group_end()
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();

        if (count($existingRows) > 0) {
            // Check if matches subject
            $first = $existingRows[0];
            $subjectMatches = ($first['intake_id'] === $intakeId) &&
                (($first['client_id_at_event'] === null && $clientId === null) || ((int) $first['client_id_at_event'] === $clientId));

            if (!$subjectMatches || count($existingRows) !== count($items)) {
                return ['ok' => false, 'reason' => 'idempotency_conflict'];
            }

            $events = [];
            $eventIds = [];
            foreach ($existingRows as $row) {
                $eventIds[] = $row['event_id'];
                $events[] = [
                    'event_id' => $row['event_id'],
                    'document_type' => $row['document_type_snapshot'],
                    'version' => $row['document_version_snapshot'],
                    'content_hash' => $row['document_hash_snapshot'],
                    'action' => $row['action'],
                    'occurred_at' => $row['occurred_at'],
                ];
            }

            return ['ok' => true, 'replayed' => true, 'event_ids' => $eventIds, 'events' => $events];
        }

        // Fetch currently effective versions
        $currentResult = $this->getCurrentVersions();
        if (!$currentResult['ok']) {
            return $currentResult;
        }
        $currentVersions = $currentResult['versions'];

        // Validate each manifestation item
        $validatedItems = [];
        $seenDocTypes = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                return ['ok' => false, 'reason' => 'invalid_manifestation_item'];
            }

            $docVerId = (int) ($item['document_version_id'] ?? 0);
            $action = trim((string) ($item['action'] ?? ''));

            $verRow = $this->getVersionById($docVerId);
            if (!$verRow) {
                return ['ok' => false, 'reason' => 'invalid_document_version'];
            }

            $docType = $verRow['document_type'];
            if (isset($seenDocTypes[$docType])) {
                return ['ok' => false, 'reason' => 'duplicate_document_type_in_request'];
            }
            $seenDocTypes[$docType] = true;

            // Action check
            if ($docType === 'TERMS_OF_USE' && $action !== 'ACCEPTED') {
                return ['ok' => false, 'reason' => 'invalid_legal_action'];
            }
            if ($docType === 'PRIVACY_POLICY' && $action !== 'ACKNOWLEDGED') {
                return ['ok' => false, 'reason' => 'invalid_legal_action'];
            }

            // Version race check: offered version MUST match currently effective version
            $effective = $currentVersions[$docType] ?? null;
            if (!$effective || (int) $effective['document_version_id'] !== $docVerId) {
                return ['ok' => false, 'reason' => 'legal_version_changed'];
            }

            $validatedItems[] = [
                'version' => $verRow,
                'action' => $action,
            ];
        }

        // Atomic append to ledger
        $this->db->trans_begin();

        $nowUtc = gmdate('Y-m-d H:i:s');
        $eventIds = [];
        $events = [];

        $isSingle = count($validatedItems) === 1;
        foreach ($validatedItems as $entry) {
            $v = $entry['version'];
            $act = $entry['action'];
            $eventId = $this->uuid();
            $itemKey = $isSingle ? $idempotencyKey : ($idempotencyKey . ':' . $v['document_type']);

            $insertData = [
                'event_id' => $eventId,
                'idempotency_key' => $itemKey,
                'document_version_id' => (int) $v['id'],
                'intake_id' => $intakeId,
                'client_id_at_event' => $clientId,
                'action' => $act,
                'occurred_at' => $nowUtc,
                'source' => substr(trim((string) ($payload['source'] ?? 'customer_registration')), 0, 64),
                'capability_id' => !empty($payload['capability_id']) ? substr(trim((string) $payload['capability_id']), 0, 64) : null,
                'ip_address' => !empty($payload['client_ip']) ? substr(trim((string) $payload['client_ip']), 0, 45) : null,
                'user_agent' => !empty($payload['user_agent']) ? substr(trim((string) $payload['user_agent']), 0, 255) : null,
                'document_type_snapshot' => $v['document_type'],
                'document_version_snapshot' => $v['version'],
                'document_hash_snapshot' => $v['content_hash'],
            ];

            if (!$this->db->insert('tecnina_legal_acceptances', $insertData)) {
                $this->db->trans_rollback();
                return ['ok' => false, 'reason' => 'database_insert_failed'];
            }

            $eventIds[] = $eventId;
            $events[] = [
                'event_id' => $eventId,
                'document_type' => $v['document_type'],
                'version' => $v['version'],
                'content_hash' => $v['content_hash'],
                'action' => $act,
                'occurred_at' => $nowUtc,
            ];
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'reason' => 'transaction_failed'];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'replayed' => false,
            'event_ids' => $eventIds,
            'events' => $events,
        ];
    }

    private function normalizeUtcTime(string $timeStr): ?string
    {
        $ts = strtotime($timeStr);
        if ($ts === false) {
            return null;
        }
        return gmdate('Y-m-d H:i:s', $ts);
    }

    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 15) | 64);
        $b[8] = chr((ord($b[8]) & 63) | 128);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
