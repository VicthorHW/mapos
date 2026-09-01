<div class="widget-box">
    <div class="widget-title" style="margin: -20px 0 0">
        <span class="icon">
            <i class="fas fa-cash-register"></i>
        </span>
        <h5>Cobranças</h5>
    </div>
    <div class="widget-content nopadding tab-content">
        <div class="c-table-responsive">
        <table id="tabela" class="table table-bordered ">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Data de Vencimento</th>
                    <th>Referência</th>
                    <th>Status</th>
                    <th>Valor</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php

                    if (!$results) {
                        echo '<tr>
                                <td colspan="5">Nenhuma cobrança Cadastrada</td>
                            </tr>';
                    }
                foreach ($results as $r) {
                    $dataVenda = date(('d/m/Y'), strtotime($r->expire_at));
                    $cobrancaStatus = getCobrancaTransactionStatus(
                        $this->config->item('payment_gateways'),
                        $r->payment_gateway,
                        $r->status
                    );

                    echo '<tr>';
                    echo '<td>' . $r->charge_id . '</td>';
                    echo '<td>' . $dataVenda . '</td>';

                    if ($r->os_id != '') {
                        echo '<td><a href="' . cliente_url('mine/visualizarOs/' . $r->os_id) . '">  Ordem de Serviço: #' . $r->os_id . '</a></td>';
                    }
                    if ($r->vendas_id != '') {
                        echo '<td><a href="' . cliente_url('mine/visualizarCompra/' . $r->vendas_id) . '">  Venda: #' . $r->vendas_id . '</a></td>';
                    }

                    echo '<td>' . $cobrancaStatus . '</td>';
                    echo '<td>R$ ' . number_format($r->total / 100, 2, ',', '.') . '</td>';
                    echo '<td>';
                    if ($this->permission->checkPermission($this->session->userdata('permissao'), 'vCobranca')) {
                        echo '<a style="margin-right: 1%" href="' . cliente_url('mine/atualizarcobranca/' . $r->idCobranca) . '"  class="btn-nwe" title="Atualizar Cobrança"><i class="bx bx-refresh"></i></a>';
                    }

                    if ($this->permission->checkPermission($this->session->userdata('permissao'), 'eCobranca')) {
                        echo '<a style="margin-right: 1%" href="' . $r->link . '"  target="_blank" class="btn-nwe" title="Visualizar boleto"><i class="bx bx-barcode" ></i></a>';
                        echo '<a style="margin-right: 1%" href="' . cliente_url('mine/enviarcobranca/' . $r->idCobranca) . '" class="btn-nwe2" title="Reenviar por email"><i class="bx bx-mail-send" ></i></a>';
                    }
                    echo '<a style="margin-right: 1%" href="' . $r->link . '"  target="_blank" class="btn-nwe" title="Visualizar boleto"><i class="bx bx-barcode" ></i></a>';
                    echo '</td>';
                    echo '</tr>';
                } ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php echo $this->pagination->create_links(); ?>
