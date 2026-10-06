<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Tecnina_notifications
 *
 * Governed by Technical Order 85, S10-INTEGRATION-SECURITY-REGRESSION.md,
 * and ADJ-020 (Ajustes §10.4):
 * "E-mail só recebe notificação quando real e confirmado.
 *  E-mail técnico ou não confirmado nunca recebe disparos."
 *
 * Validates whether email notifications are allowed based on the customer's
 * identity verification state in `tecnina_client_identity` (VERIFIED or LEGACY_EXISTING).
 * Suppresses notifications for NONE or PENDING states.
 */
class Tecnina_notifications
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Determines if email notification is authorized for a customer.
     *
     * @param int|null $customerId
     * @param string|null $email
     * @return bool
     */
    public function isEmailNotificationAllowed($customerId = null, $email = null)
    {
        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $customerId = (int) $customerId;
        if ($customerId <= 0) {
            // If no customer ID is linked, only valid non-empty email syntax is checked
            return $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        }

        $identity = $this->CI->db->select('email_state, email_candidate')
            ->where('client_id', $customerId)
            ->get('tecnina_client_identity')
            ->row();

        if ($identity) {
            // ADJ-020: PENDING and NONE are strictly prohibited from receiving notifications
            if (in_array($identity->email_state, ['NONE', 'PENDING'], true)) {
                return false;
            }

            // VERIFIED and LEGACY_EXISTING are permitted
            if (in_array($identity->email_state, ['VERIFIED', 'LEGACY_EXISTING'], true)) {
                return true;
            }

            return false;
        }

        // Backward compatibility for pre-S03 clients without identity row: allow if email is valid
        return $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Filters a list of recipient emails for a specific customer or list of customers.
     *
     * @param array $recipients Array of email addresses or ['client_id' => ..., 'email' => ...]
     * @param int|null $customerId Default customerId if recipients is just list of emails
     * @return array Allowed recipient emails
     */
    public function filterRecipients(array $recipients, $customerId = null)
    {
        $allowed = [];
        foreach ($recipients as $item) {
            if (is_array($item)) {
                $cId = (int) ($item['client_id'] ?? $customerId);
                $mail = (string) ($item['email'] ?? '');
            } else {
                $cId = $customerId;
                $mail = (string) $item;
            }

            if ($this->isEmailNotificationAllowed($cId, $mail)) {
                $allowed[] = $mail;
            } else {
                log_message('info', "Email notification suppressed for unconfirmed recipient: {$mail} (client_id: {$cId}) (ADJ-020)");
            }
        }

        return array_values(array_unique($allowed));
    }

    /**
     * Enqueues an email to `email_queue` only if authorized by ADJ-020.
     *
     * @param string $to
     * @param string $message
     * @param string $subject
     * @param int|null $customerId
     * @param array $extraHeaders
     * @return bool
     */
    public function queueEmail($to, $message, $subject, $customerId = null, array $extraHeaders = [])
    {
        if (! $this->isEmailNotificationAllowed($customerId, $to)) {
            log_message('info', "Email enqueue suppressed for unconfirmed recipient: {$to} (client_id: {$customerId}) (ADJ-020)");

            return false;
        }

        $this->CI->load->model('email_model');
        $this->CI->load->model('mapos_model');

        $emitente = $this->CI->mapos_model->getEmitente();
        if (! $emitente || ! filter_var($emitente->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $headers = array_merge([
            'From' => "\"$emitente->nome\" <$emitente->email>",
            'Subject' => $subject,
            'Return-Path' => '',
        ], $extraHeaders);

        return (bool) $this->CI->email_model->add('email_queue', [
            'to' => $to,
            'message' => $message,
            'status' => 'pending',
            'date' => date('Y-m-d H:i:s'),
            'headers' => json_encode($headers),
        ]);
    }
}
