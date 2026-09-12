<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';

class Client_profile extends REST_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('Tecnina_bot_auth');
        $this->load->library('Tecnina_phone');
        $this->load->model('Tecnina_bot_client_model');
    }

    public function index_get($clientId = null)
    {
        if (! $this->authorizeRequest() || ! $this->validId($clientId)) {
            return;
        }
        $row = $this->Tecnina_bot_client_model->profile((int) $clientId);
        if (! $row) {
            $this->response(['status' => false, 'reason' => 'client_not_found'], self::HTTP_NOT_FOUND);

            return;
        }
        $this->response($this->profileResponse($row), self::HTTP_OK);
    }

    public function index_patch($clientId = null)
    {
        if (! $this->authorizeRequest() || ! $this->validId($clientId)) {
            return;
        }
        $input = $this->patch();
        if (! is_array($input) || array_diff(array_keys($input), ['name', 'address']) !== []) {
            $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_BAD_REQUEST);

            return;
        }
        $changes = [];
        if (array_key_exists('name', $input)) {
            $name = $this->bounded($input['name'], 255, false);
            if ($name === false) {
                $this->response(['status' => false, 'reason' => 'invalid_name'], self::HTTP_UNPROCESSABLE_ENTITY);

                return;
            }
            $changes['nomeCliente'] = $name;
        }
        if (array_key_exists('address', $input)) {
            $address = $this->address($input['address']);
            if ($address === false) {
                $this->response(['status' => false, 'reason' => 'invalid_address'], self::HTTP_UNPROCESSABLE_ENTITY);

                return;
            }
            $changes = array_merge($changes, $address);
        }
        if (! $this->Tecnina_bot_client_model->updateProfile((int) $clientId, $changes)) {
            $this->response(['status' => false, 'reason' => 'client_not_found'], self::HTTP_NOT_FOUND);

            return;
        }
        $this->response(
            $this->profileResponse($this->Tecnina_bot_client_model->profile((int) $clientId)),
            self::HTTP_OK
        );
    }

    public function unlink_phone_post($clientId = null)
    {
        if (! $this->authorizeRequest() || ! $this->validId($clientId)) {
            return;
        }
        $input = $this->post();
        $phone = $this->tecnina_phone->normalizeIdentity($input['phone'] ?? '');
        if (! is_array($input) || array_keys($input) !== ['phone'] || $phone === null) {
            $this->response(['status' => false, 'reason' => 'invalid_phone'], self::HTTP_UNPROCESSABLE_ENTITY);

            return;
        }
        if (! $this->Tecnina_bot_client_model->unlinkPhone((int) $clientId, $phone, $this->tecnina_phone)) {
            $this->response(['status' => false, 'reason' => 'phone_link_changed'], self::HTTP_CONFLICT);

            return;
        }
        $this->response(['status' => true, 'unlinked' => true], self::HTTP_OK);
    }

    private function profileResponse(array $row)
    {
        $phone = $this->tecnina_phone->canonicalIdentityFromStored($row['celular'])
            ?: $this->tecnina_phone->canonicalIdentityFromStored($row['telefone']);
        $address = null;
        if (trim((string) $row['rua']) !== '' && trim((string) $row['cidade']) !== '') {
            $address = [
                'postal_code' => preg_replace('/\D+/', '', (string) $row['cep']) ?: null,
                'street' => $this->nullable($row['rua']),
                'street_number' => $this->nullable($row['numero']),
                'neighborhood' => $this->nullable($row['bairro']),
                'city' => $this->nullable($row['cidade']),
                'state' => $this->nullable($row['estado']),
                'complement' => $this->nullable($row['complemento']),
                'reference' => null,
            ];
        }

        return [
            'status' => true,
            'client_id' => (int) $row['idClientes'],
            'name' => (string) $row['nomeCliente'],
            'phone' => $phone ?: '',
            'address' => $address,
        ];
    }

    private function address($value)
    {
        if (! is_array($value) || array_diff(array_keys($value), [
            'postal_code', 'street', 'street_number', 'neighborhood', 'city',
            'state', 'complement', 'reference',
        ]) !== []) {
            return false;
        }
        $postal = preg_replace('/\D+/', '', (string) ($value['postal_code'] ?? ''));
        $street = $this->bounded($value['street'] ?? null, 160, false);
        $number = $this->bounded($value['street_number'] ?? null, 32, false);
        $neighborhood = $this->bounded($value['neighborhood'] ?? null, 120, false);
        $city = $this->bounded($value['city'] ?? null, 80, false);
        $state = strtoupper((string) ($value['state'] ?? ''));
        $complement = $this->bounded($value['complement'] ?? null, 160, true);
        if (strlen($postal) !== 8 || $street === false || $number === false
            || $neighborhood === false || $city === false
            || preg_match('/^[A-Z]{2}$/', $state) !== 1 || $complement === false) {
            return false;
        }

        return [
            'cep' => $postal,
            'rua' => $street,
            'numero' => $number,
            'bairro' => $neighborhood,
            'cidade' => $city,
            'estado' => $state,
            'complemento' => $complement,
        ];
    }

    private function bounded($value, $max, $nullable)
    {
        if ($value === null && $nullable) {
            return null;
        }
        if (! is_string($value)) {
            return false;
        }
        $value = trim($value);

        return ($value === '' && $nullable) ? null : (mb_strlen($value) >= 1 && mb_strlen($value) <= $max ? $value : false);
    }

    private function nullable($value)
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function validId($value)
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
            $this->response(['status' => false, 'reason' => 'invalid_client_id'], self::HTTP_BAD_REQUEST);

            return false;
        }

        return true;
    }

    private function authorizeRequest()
    {
        $auth = $this->tecnina_bot_auth->authorize($this->input->get_request_header('Authorization', true));
        if (! $auth['ok']) {
            $this->response(['status' => false, 'reason' => $auth['reason']], $auth['status']);

            return false;
        }

        return true;
    }
}
