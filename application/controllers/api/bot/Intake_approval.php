<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';

/**
 * Intake_approval (Legacy)
 *
 * @deprecated Deprecated since CIAO-S07. Materialization is strictly handled
 *             via Tecnina_materialization_service by authenticated staff.
 */
class Intake_approval extends REST_Controller
{
    public function index_post($intakeId = null)
    {
        $this->response([
            'status' => false,
            'reason' => 'endpoint_deprecated',
            'message' => 'Legacy intake approval endpoint is deprecated and disabled (HTTP 410 Gone). Materialization must be executed via Tecnina_materialization_service.',
        ], self::HTTP_GONE);
    }
}
