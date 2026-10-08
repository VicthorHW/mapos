<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Clientes extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('clientes_model');
        $this->data['menuClientes'] = 'clientes';
    }

    public function index()
    {
        $this->gerenciar();
    }

    public function gerenciar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar clientes.');
            redirect(base_url());
        }

        $pesquisa = $this->input->get('pesquisa');

        $this->load->library('pagination');

        $this->data['configuration']['base_url'] = site_url('clientes/gerenciar/');
        $this->data['configuration']['total_rows'] = $this->clientes_model->count('clientes');
        if ($pesquisa) {
            $this->data['configuration']['suffix'] = "?pesquisa={$pesquisa}";
            $this->data['configuration']['first_url'] = base_url("index.php/clientes")."\?pesquisa={$pesquisa}";
        }

        $this->pagination->initialize($this->data['configuration']);

        $this->data['results'] = $this->clientes_model->get('clientes', '*', $pesquisa, $this->data['configuration']['per_page'], $this->uri->segment(3));

        $this->data['view'] = 'clientes/clientes';

        return $this->layout();
    }

    public function adicionar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar clientes.');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->data['custom_error'] = '';

        $senhaCliente = $this->input->post('senha') ? $this->input->post('senha') : preg_replace('/[^\p{L}\p{N}\s]/', '', set_value('documento'));

        $cpf_cnpj = preg_replace('/[^\p{L}\p{N}\s]/', '', set_value('documento'));

        if (strlen($cpf_cnpj) == 11) {
            $pessoa_fisica = true;
        } else {
            $pessoa_fisica = false;
        }

        if ($this->form_validation->run('clientes') == false) {
            $this->data['custom_error'] = (validation_errors() ? '<div class="form_error">' . validation_errors() . '</div>' : false);
        } else {
            $email = set_value('email');
            if ($email && $this->clientes_model->emailExists($email)) {
                $this->data['custom_error'] = '<div class="form_error"><p>Este e-mail já está sendo utilizado por outro cliente.</p></div>';
            } else {
                $data = [
                'nomeCliente' => set_value('nomeCliente'),
                'contato' => set_value('contato'),
                'pessoa_fisica' => $pessoa_fisica,
                'documento' => set_value('documento'),
                'telefone' => set_value('telefone'),
                'celular' => set_value('celular'),
                'email' => set_value('email'),
                'senha' => password_hash($senhaCliente, PASSWORD_DEFAULT),
                'rua' => set_value('rua'),
                'numero' => set_value('numero'),
                'complemento' => set_value('complemento'),
                'bairro' => set_value('bairro'),
                'cidade' => set_value('cidade'),
                'estado' => set_value('estado'),
                'cep' => set_value('cep'),
                'dataCadastro' => date('Y-m-d'),
                'fornecedor' => $this->input->post('fornecedor') ? 1 : 0,
            ];

                $clienteId = $this->clientes_model->add('clientes', $data);
                if ($clienteId) {
                    $emailBoasVindasEnfileirado = false;
                    if (! $data['fornecedor'] && ! empty($data['email'])) {
                        $this->load->library('customer_welcome_email');
                        $emailBoasVindasEnfileirado = $this->customer_welcome_email->queue($clienteId);
                    }

                    $intakeId = trim((string) $this->input->post('intake_id'));
                    if ($intakeId !== '' && preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $intakeId)) {
                        $this->load->library('tecnina_phone');
                        $this->load->library('tecnina_bot_gateway');

                        // Fetch pre-attendance draft to obtain canonical phone and review_version
                        $intakeResp = $this->tecnina_bot_gateway->request('GET', '/admin/intakes/' . rawurlencode($intakeId));

                        $canonicalPhone = null;
                        if ($intakeResp['ok'] && ! empty($intakeResp['data']['phone_canonical'])) {
                            $canonicalPhone = (string) $intakeResp['data']['phone_canonical'];
                        } else {
                            $rawPhone = ! empty($data['celular']) ? $data['celular'] : $data['telefone'];
                            $candidates = $this->tecnina_phone->candidateIdentities($rawPhone, null);
                            $canonicalPhone = ! empty($candidates) ? $candidates[0] : null;
                        }

                        // Register / update canonical identity in tecnina_client_identity (VERIFIED)
                        $nowUtc = gmdate('Y-m-d H:i:s');
                        $existingIdent = $this->db->where('client_id', (int) $clienteId)->get('tecnina_client_identity')->row();
                        if ($existingIdent) {
                            $this->db->where('client_id', (int) $clienteId)->update('tecnina_client_identity', [
                                'canonical_phone' => $canonicalPhone,
                                'phone_state' => $canonicalPhone ? 'VERIFIED' : 'NONE',
                                'phone_confirmed_at' => $canonicalPhone ? $nowUtc : null,
                            ]);
                        } else {
                            $this->db->insert('tecnina_client_identity', [
                                'client_id' => (int) $clienteId,
                                'canonical_phone' => $canonicalPhone,
                                'phone_state' => $canonicalPhone ? 'VERIFIED' : 'NONE',
                                'phone_confirmed_at' => $canonicalPhone ? $nowUtc : null,
                                'email_candidate' => ! empty($data['email']) ? $data['email'] : null,
                                'email_state' => ! empty($data['email']) ? 'PENDING' : 'NONE',
                                'credential_version' => 1,
                            ]);
                        }

                        // Save profile info (birth_date) if present in intake
                        if ($intakeResp['ok'] && is_array($intakeResp['data'])) {
                            $intakeData = $intakeResp['data'];
                            $birthDate = ! empty($intakeData['birth_date']) ? $intakeData['birth_date'] : null;
                            $addressRef = ! empty($intakeData['registration_reference']) ? mb_substr(trim((string) $intakeData['registration_reference']), 0, 255) : null;
                            if ($birthDate !== null || $addressRef !== null) {
                                $profileRow = $this->db->where('client_id', (int) $clienteId)->get('tecnina_client_profile')->row();
                                if ($profileRow) {
                                    $this->db->where('client_id', (int) $clienteId)->update('tecnina_client_profile', [
                                        'birth_date' => $birthDate,
                                        'address_reference' => $addressRef,
                                    ]);
                                } else {
                                    $this->db->insert('tecnina_client_profile', [
                                        'client_id' => (int) $clienteId,
                                        'birth_date' => $birthDate,
                                        'address_reference' => $addressRef,
                                    ]);
                                }
                            }

                            // Link customer to the intake draft via bot gateway
                            if (isset($intakeData['review_version'])) {
                                $this->tecnina_bot_gateway->request('PUT', '/admin/intakes/' . rawurlencode($intakeId), [
                                    'review_version' => (int) $intakeData['review_version'],
                                    'possible_mapos_client_id' => (int) $clienteId,
                                ]);
                            }
                        }

                        $mensagemSucesso = 'Cliente cadastrado com sucesso e vinculado ao pré-atendimento!';
                        $this->session->set_flashdata('success', $mensagemSucesso);
                        log_info('Adicionou um cliente vinculado ao pre-atendimento ' . $intakeId . ' (Cliente #' . $clienteId . ').');
                        redirect(site_url('tecnina_whatsapp/pre_atendimentos?intake_id=' . rawurlencode($intakeId)));
                        return;
                    }

                    $mensagemSucesso = 'Cliente adicionado com sucesso!';
                    if ($emailBoasVindasEnfileirado) {
                        $mensagemSucesso .= ' E-mail de boas-vindas adicionado à fila de envio.';
                    } elseif (! $data['fornecedor'] && ! empty($data['email'])) {
                        $mensagemSucesso .= ' O e-mail de boas-vindas não foi adicionado à fila; verifique o cadastro do emitente e os logs.';
                    }

                    $this->session->set_flashdata('success', $mensagemSucesso);
                    log_info('Adicionou um cliente.');
                    redirect(site_url('clientes/'));
                } else {
                    $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um erro.</p></div>';
                }
            }
        }

        $this->data['view'] = 'clientes/adicionarCliente';

        return $this->layout();
    }

    public function editar()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3)) || ! $this->clientes_model->getById($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Cliente não encontrado ou parâmetro inválido.');
            redirect('clientes/gerenciar');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar clientes.');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->data['custom_error'] = '';

        if ($this->form_validation->run('clientes') == false) {
            $this->data['custom_error'] = (validation_errors() ? '<div class="form_error">' . validation_errors() . '</div>' : false);
        } else {
            
            $email = $this->input->post('email');
            $idCliente = $this->input->post('idClientes');
            if ($email && $this->clientes_model->emailExists($email, $idCliente)) {
                $this->data['custom_error'] = '<div class="form_error"><p>Este e-mail já está sendo utilizado por outro cliente.</p></div>';
            } else {
                $senha = $this->input->post('senha');
                if ($senha != null) {
                    $senha = password_hash($senha, PASSWORD_DEFAULT);

                    $data = [
                        'nomeCliente' => $this->input->post('nomeCliente'),
                        'contato' => $this->input->post('contato'),
                        'documento' => $this->input->post('documento'),
                        'telefone' => $this->input->post('telefone'),
                        'celular' => $this->input->post('celular'),
                        'email' => $this->input->post('email'),
                        'senha' => $senha,
                        'rua' => $this->input->post('rua'),
                        'numero' => $this->input->post('numero'),
                        'complemento' => $this->input->post('complemento'),
                        'bairro' => $this->input->post('bairro'),
                        'cidade' => $this->input->post('cidade'),
                        'estado' => $this->input->post('estado'),
                        'cep' => $this->input->post('cep'),
                        'fornecedor' => (set_value('fornecedor') == true ? 1 : 0),
                    ];
                } else {
                    $data = [
                        'nomeCliente' => $this->input->post('nomeCliente'),
                        'contato' => $this->input->post('contato'),
                        'documento' => $this->input->post('documento'),
                        'telefone' => $this->input->post('telefone'),
                        'celular' => $this->input->post('celular'),
                        'email' => $this->input->post('email'),
                        'rua' => $this->input->post('rua'),
                        'numero' => $this->input->post('numero'),
                        'complemento' => $this->input->post('complemento'),
                        'bairro' => $this->input->post('bairro'),
                        'cidade' => $this->input->post('cidade'),
                        'estado' => $this->input->post('estado'),
                        'cep' => $this->input->post('cep'),
                        'fornecedor' => (set_value('fornecedor') == true ? 1 : 0),
                    ];
                }

                if ($this->clientes_model->edit('clientes', $data, 'idClientes', $this->input->post('idClientes')) == true) {
                    $this->session->set_flashdata('success', 'Cliente editado com sucesso!');
                    log_info('Alterou um cliente. ID' . $this->input->post('idClientes'));
                    redirect(site_url('clientes/editar/') . $this->input->post('idClientes'));
                } else {
                    $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um erro</p></div>';
                }
            }
        }

        $this->data['result'] = $this->clientes_model->getById($this->uri->segment(3));
        $this->data['view'] = 'clientes/editarCliente';

        return $this->layout();
    }

    public function visualizar()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('mapos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar clientes.');
            redirect(base_url());
        }

        $this->data['custom_error'] = '';
        $this->data['result'] = $this->clientes_model->getById($this->uri->segment(3));
        $this->data['results'] = $this->clientes_model->getOsByCliente($this->uri->segment(3));
        $this->data['result_vendas'] = $this->clientes_model->getAllVendasByClient($this->uri->segment(3));
        $this->data['view'] = 'clientes/visualizar';

        return $this->layout();
    }

    public function excluir()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'dCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para excluir clientes.');
            redirect(base_url());
        }

        $id = $this->input->post('id');
        if ($id == null) {
            $this->session->set_flashdata('error', 'Erro ao tentar excluir cliente.');
            redirect(site_url('clientes/gerenciar/'));
        }

        $os = $this->clientes_model->getAllOsByClient($id);
        if ($os != null) {
            $this->clientes_model->removeClientOs($os);
        }

        // excluindo Vendas vinculadas ao cliente
        $vendas = $this->clientes_model->getAllVendasByClient($id);
        if ($vendas != null) {
            $this->clientes_model->removeClientVendas($vendas);
        }

        $this->clientes_model->delete('clientes', 'idClientes', $id);
        log_info('Removeu um cliente. ID' . $id);

        $this->session->set_flashdata('success', 'Cliente excluido com sucesso!');
        redirect(site_url('clientes/gerenciar/'));
    }
}
