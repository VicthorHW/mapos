<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Model: Tecnina_receiving_model
 *
 * Authoritative MapOS physical receiving and pre-OS attachments model.
 * Governed by CIAO-S06A, ADR-004, and ADR-005.
 *
 * Enforces:
 * - One physical receiving record per intake
 * - Lifecycle: PENDING_DELIVERY -> RECEIVED -> CANCELLED
 * - Explicit staff action for RECEIVED state (never inferred)
 * - Server-authoritative operator ID from session
 * - Idempotency key tracking and conflict detection
 * - Private attachment metadata (storage outside web root)
 * - GPS original vs adjusted preservation
 * - Absolute zero OS creation
 */
class Tecnina_receiving_model extends CI_Model
{
    private const ALLOWED_STATES = ['PENDING_DELIVERY', 'RECEIVED', 'CANCELLED'];

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Get the physical receiving record for an intake.
     */
    public function getReceiving(string $intakeId): ?array
    {
        $table = $this->db->dbprefix('tecnina_physical_receiving');
        $row = $this->db->get_where($table, ['intake_id' => $intakeId])->row_array();

        if (! $row) {
            return null;
        }

        // Attach operator name if received_by is set
        if (! empty($row['received_by'])) {
            $user = $this->db->select('nome')->get_where('usuarios', ['idUsuarios' => $row['received_by']])->row_array();
            $row['received_by_name'] = $user['nome'] ?? 'Operador #' . $row['received_by'];
        } else {
            $row['received_by_name'] = null;
        }

        return $row;
    }

    /**
     * Save receiving preparation data without marking as RECEIVED.
     * State remains PENDING_DELIVERY (or new record created in PENDING_DELIVERY).
     */
    public function savePreparation(string $intakeId, array $data, ?int $operatorId = null): array
    {
        $table = $this->db->dbprefix('tecnina_physical_receiving');
        $existing = $this->getReceiving($intakeId);

        $record = [
            'device_condition' => isset($data['device_condition']) ? mb_substr(trim((string) $data['device_condition']), 0, 64) : null,
            'accessories' => isset($data['accessories']) ? trim((string) $data['accessories']) : null,
            'serial_number' => isset($data['serial_number']) ? mb_substr(trim((string) $data['serial_number']), 0, 128) : null,
            'imei' => isset($data['imei']) ? mb_substr(trim((string) $data['imei']), 0, 32) : null,
            'other_identifiers' => isset($data['other_identifiers']) ? trim((string) $data['other_identifiers']) : null,
            'notes' => isset($data['notes']) ? trim((string) $data['notes']) : null,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        if ($existing) {
            // If already received, do not regress state
            $this->db->where('intake_id', $intakeId)->update($table, $record);
        } else {
            $record['intake_id'] = $intakeId;
            $record['state'] = 'PENDING_DELIVERY';
            $record['created_at'] = gmdate('Y-m-d H:i:s');
            $this->db->insert($table, $record);
        }

        return $this->getReceiving($intakeId);
    }

    /**
     * Explicitly confirm physical receipt of equipment by authenticated staff.
     * Sets state to RECEIVED, persists UTC timestamp and operator from session.
     * Handles Idempotency-Key.
     */
    public function confirmPhysicalReceipt(string $intakeId, int $operatorId, array $data, ?string $idempotencyKey = null): array
    {
        if ($operatorId <= 0) {
            return ['ok' => false, 'reason' => 'invalid_operator'];
        }

        // Verify operator exists in MapOS usuarios
        $operator = $this->db->select('idUsuarios, nome')
            ->from('usuarios')
            ->where('idUsuarios', $operatorId)
            ->where('situacao', 1)
            ->limit(1)
            ->get()
            ->row_array();

        if (! $operator) {
            return ['ok' => false, 'reason' => 'invalid_operator'];
        }

        $table = $this->db->dbprefix('tecnina_physical_receiving');
        $existing = $this->getReceiving($intakeId);

        // Normalize payload for semantic request hash
        $payloadForHash = [
            'device_condition' => isset($data['device_condition']) ? trim((string) $data['device_condition']) : '',
            'accessories' => isset($data['accessories']) ? trim((string) $data['accessories']) : '',
            'serial_number' => isset($data['serial_number']) ? trim((string) $data['serial_number']) : '',
            'imei' => isset($data['imei']) ? trim((string) $data['imei']) : '',
            'other_identifiers' => isset($data['other_identifiers']) ? trim((string) $data['other_identifiers']) : '',
            'notes' => isset($data['notes']) ? trim((string) $data['notes']) : '',
        ];
        $requestHash = hash('sha256', json_encode($payloadForHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Idempotency check if already RECEIVED
        if ($existing && $existing['state'] === 'RECEIVED') {
            if ($idempotencyKey !== null && ! empty($existing['idempotency_key'])) {
                if ($existing['idempotency_key'] === $idempotencyKey) {
                    if ($existing['request_hash'] === $requestHash) {
                        return [
                            'ok' => true,
                            'result' => 'already_received',
                            'receiving_id' => (int) $existing['id'],
                            'state' => 'RECEIVED',
                            'received_at' => $existing['received_at'],
                            'received_by' => (int) $existing['received_by'],
                            'data' => $existing,
                        ];
                    }

                    // Same idempotency key with materially different payload -> CONFLICT
                    return ['ok' => false, 'reason' => 'idempotency_conflict'];
                }
            }

            // Same semantic data repeated without key -> reuse existing record
            if ($existing['request_hash'] === $requestHash) {
                return [
                    'ok' => true,
                    'result' => 'already_received',
                    'receiving_id' => (int) $existing['id'],
                    'state' => 'RECEIVED',
                    'received_at' => $existing['received_at'],
                    'received_by' => (int) $existing['received_by'],
                    'data' => $existing,
                ];
            }
        }

        $nowUtc = gmdate('Y-m-d H:i:s');
        $record = [
            'state' => 'RECEIVED',
            'received_at' => $nowUtc,
            'received_by' => $operatorId,
            'device_condition' => mb_substr($payloadForHash['device_condition'], 0, 64) ?: null,
            'accessories' => $payloadForHash['accessories'] ?: null,
            'serial_number' => mb_substr($payloadForHash['serial_number'], 0, 128) ?: null,
            'imei' => mb_substr($payloadForHash['imei'], 0, 32) ?: null,
            'other_identifiers' => $payloadForHash['other_identifiers'] ?: null,
            'notes' => $payloadForHash['notes'] ?: null,
            'idempotency_key' => $idempotencyKey ? mb_substr($idempotencyKey, 0, 64) : null,
            'request_hash' => $requestHash,
            'updated_at' => $nowUtc,
        ];

        if ($existing) {
            $this->db->where('intake_id', $intakeId)->update($table, $record);
            $receivingId = (int) $existing['id'];
        } else {
            $record['intake_id'] = $intakeId;
            $record['created_at'] = $nowUtc;
            $this->db->insert($table, $record);
            $receivingId = (int) $this->db->insert_id();
        }

        $updated = $this->getReceiving($intakeId);

        return [
            'ok' => true,
            'result' => 'received',
            'receiving_id' => $receivingId,
            'state' => 'RECEIVED',
            'received_at' => $nowUtc,
            'received_by' => $operatorId,
            'data' => $updated,
        ];
    }

    /**
     * Save an attachment record in tecnina_pre_os_attachments.
     */
    public function saveAttachment(
        string $intakeId,
        string $originalName,
        string $storageKey,
        ?string $detectedMime,
        int $sizeBytes,
        string $sha256,
        ?string $caption = null
    ): array {
        $table = $this->db->dbprefix('tecnina_pre_os_attachments');
        $now = gmdate('Y-m-d H:i:s');

        $record = [
            'intake_id' => $intakeId,
            'original_name' => mb_substr($originalName, 0, 255),
            'storage_key' => mb_substr($storageKey, 0, 255),
            'detected_mime' => $detectedMime ? mb_substr($detectedMime, 0, 127) : null,
            'size_bytes' => $sizeBytes,
            'sha256' => $sha256,
            'state' => 'STAGED',
            'caption' => $caption ? mb_substr($caption, 0, 255) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->db->insert($table, $record);
        $insertId = (int) $this->db->insert_id();

        $record['id'] = $insertId;

        return $record;
    }

    /**
     * Get all attachments for an intake.
     */
    public function getAttachments(string $intakeId): array
    {
        $table = $this->db->dbprefix('tecnina_pre_os_attachments');
        return $this->db
            ->from($table)
            ->where('intake_id', $intakeId)
            ->where('state !=', 'PURGED')
            ->order_by('created_at', 'ASC')
            ->get()
            ->result_array();
    }

    /**
     * Get a single attachment by intake_id and attachment_id.
     */
    public function getAttachment(string $intakeId, int $attachmentId): ?array
    {
        $table = $this->db->dbprefix('tecnina_pre_os_attachments');
        $row = $this->db
            ->from($table)
            ->where('intake_id', $intakeId)
            ->where('id', $attachmentId)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    /**
     * Delete an attachment record (marks PURGED or removes).
     */
    public function deleteAttachment(string $intakeId, int $attachmentId): bool
    {
        $table = $this->db->dbprefix('tecnina_pre_os_attachments');
        $this->db->where('intake_id', $intakeId)->where('id', $attachmentId)->delete($table);
        return $this->db->affected_rows() > 0;
    }

    /**
     * Get total size in bytes of all active attachments for an intake.
     */
    public function getTotalAttachmentBytes(string $intakeId): int
    {
        $table = $this->db->dbprefix('tecnina_pre_os_attachments');
        $row = $this->db
            ->select_sum('size_bytes', 'total_bytes')
            ->from($table)
            ->where('intake_id', $intakeId)
            ->where('state !=', 'PURGED')
            ->get()
            ->row_array();

        return (int) ($row['total_bytes'] ?? 0);
    }

    /**
     * Get GPS location record for an intake.
     */
    public function getLocation(string $intakeId): ?array
    {
        $table = $this->db->dbprefix('tecnina_intake_locations');
        $row = $this->db->get_where($table, ['intake_id' => $intakeId])->row_array();
        return $row ?: null;
    }

    /**
     * Save or update GPS location record, preserving original vs adjusted coordinates.
     */
    public function saveLocation(string $intakeId, array $data): array
    {
        $table = $this->db->dbprefix('tecnina_intake_locations');
        $existing = $this->getLocation($intakeId);
        $now = gmdate('Y-m-d H:i:s');

        $record = [
            'updated_at' => $now,
        ];

        if (isset($data['original_latitude']) && isset($data['original_longitude'])) {
            $record['original_latitude'] = (float) $data['original_latitude'];
            $record['original_longitude'] = (float) $data['original_longitude'];
            $record['original_accuracy_meters'] = isset($data['original_accuracy_meters']) ? (float) $data['original_accuracy_meters'] : null;
            $record['original_source'] = isset($data['original_source']) ? mb_substr(trim((string) $data['original_source']), 0, 32) : 'WEB_FORM';
            $record['original_captured_at'] = $now;
            if (! isset($record['primary_coordinate'])) {
                $record['primary_coordinate'] = 'ORIGINAL';
            }
        }

        if (isset($data['adjusted_latitude']) && isset($data['adjusted_longitude'])) {
            $record['adjusted_latitude'] = (float) $data['adjusted_latitude'];
            $record['adjusted_longitude'] = (float) $data['adjusted_longitude'];
            $record['adjusted_accuracy_meters'] = isset($data['adjusted_accuracy_meters']) ? (float) $data['adjusted_accuracy_meters'] : null;
            $record['adjusted_source'] = isset($data['adjusted_source']) ? mb_substr(trim((string) $data['adjusted_source']), 0, 32) : 'STAFF_ADJUSTED';
            $record['adjusted_at'] = $now;
            $record['primary_coordinate'] = 'ADJUSTED';
        }

        if ($existing) {
            $this->db->where('intake_id', $intakeId)->update($table, $record);
        } else {
            $record['intake_id'] = $intakeId;
            $record['created_at'] = $now;
            $this->db->insert($table, $record);
        }

        return $this->getLocation($intakeId);
    }
}
