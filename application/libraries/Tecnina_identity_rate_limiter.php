<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Database-backed fixed-window limiter for MapOS-owned identity operations. */
class Tecnina_identity_rate_limiter
{
    private $CI;
    public function __construct() { $this->CI =& get_instance(); }
    public function allow($scope, $subject, $limit, $seconds)
    {
        $transactionStarted = false;
        try {
            if (! is_string($scope) || $scope === '' || ! is_string($subject) || $subject === '' || $limit < 1 || $seconds < 1) {
                return null;
            }
            $bucket = gmdate('Y-m-d H:i:00', floor(time() / $seconds) * $seconds);
            $key = hash('sha256', $scope . '|' . $subject . '|' . $bucket);
            $this->CI->db->trans_start();
            $transactionStarted = true;
            $table = '`' . $this->CI->db->dbprefix('tecnina_identity_rate_limits') . '`';
            $row = $this->CI->db->query("SELECT `count` FROM {$table} WHERE `bucket_key` = ? FOR UPDATE", [$key])->row();
            if (! $row) {
                $this->CI->db->insert('tecnina_identity_rate_limits', ['bucket_key'=>$key,'scope'=>$scope,'bucket_start'=>$bucket,'count'=>1]);
                $allowed = true;
            } elseif ((int) $row->count < $limit) {
                $this->CI->db->where('bucket_key',$key)->set('count','count + 1',false)->update('tecnina_identity_rate_limits');
                $allowed = true;
            } else {
                $allowed = false;
            }
            $this->CI->db->trans_complete();
            return $this->CI->db->trans_status() ? $allowed : null;
        } catch (Throwable $e) {
            if ($transactionStarted) {
                $this->CI->db->trans_rollback();
            }
            return null;
        }
    }
}
