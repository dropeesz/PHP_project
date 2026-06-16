<?php
require_once "conexao.php";

$tipo = $_POST['tipo'] ?? '';

function redirecionar()
{
    header("Location: index.php");
    exit;
}

/* ===============================
   PROCESSAMENTO
   =============================== */

if ($tipo == "limpar_historico") {
    $conn->query("DELETE FROM movimentacoes");
    redirecionar();
}

if ($tipo == "valor_atraso") {
    $cotista_id = (int) ($_POST['cotista_id'] ?? 0);
    $valor = (float) ($_POST['valor'] ?? 0);
    $data = $_POST['data'] ?? '';

    if ($valor > 0 && !empty($data)) {
        $stmt = $conn->prepare("INSERT INTO movimentacoes (cotista_id, tipo, valor, data) VALUES (?, 'valor_atraso', ?, ?)");
        $stmt->bind_param("ids", $cotista_id, $valor, $data);
        $stmt->execute();
        redirecionar();
    }
}

if ($tipo == "pagamento_atraso") {
    $cotista_id = (int) ($_POST['cotista_id'] ?? 0);
    $valor = (float) ($_POST['valor'] ?? 0);
    $data = $_POST['data'] ?? '';

    if ($valor > 0 && !empty($data)) {
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN tipo = 'valor_atraso' THEN valor ELSE 0 END), 0) AS total_atraso,
                COALESCE(SUM(CASE WHEN tipo = 'pagamento_atraso' THEN valor ELSE 0 END), 0) AS total_pago
            FROM movimentacoes
            WHERE cotista_id = ?
        ");
        $stmt->bind_param("i", $cotista_id);
        $stmt->execute();
        $resultado = $stmt->get_result()->fetch_assoc();

        $total_atraso = (float) $resultado['total_atraso'];
        $total_pago = (float) $resultado['total_pago'];
        $saldo_atraso = $total_atraso - $total_pago;

        if ($saldo_atraso <= 0) {
            echo "<script>alert('Este cotista não possui valor em atraso pendente.'); window.location='index.php';</script>";
            exit;
        }

        if ($valor > $saldo_atraso) {
            echo "<script>alert('O valor informado é maior que o atraso pendente. Saldo disponível para pagamento: R$ " . number_format($saldo_atraso, 2, ',', '.') . "'); window.location='index.php';</script>";
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO movimentacoes (cotista_id, tipo, valor, data) VALUES (?, 'pagamento_atraso', ?, ?)");
        $stmt->bind_param("ids", $cotista_id, $valor, $data);
        $stmt->execute();
        redirecionar();
    }
}

if ($tipo == "pagamento_cota") {
    $cotista_id = (int) ($_POST['cotista_id'] ?? 0);
    $valor = (float) ($_POST['valor'] ?? 0);
    $bonus = (float) ($_POST['bonus'] ?? 0);
    $data = $_POST['data'] ?? '';

    if (!empty($data)) {
        if ($valor > 0) {
            $stmt = $conn->prepare("INSERT INTO movimentacoes (cotista_id, tipo, valor, data) VALUES (?, 'cota', ?, ?)");
            $stmt->bind_param("ids", $cotista_id, $valor, $data);
            $stmt->execute();
        }

        if ($bonus > 0) {
            $stmt = $conn->prepare("INSERT INTO movimentacoes (cotista_id, tipo, valor, data) VALUES (?, 'bonus_cota', ?, ?)");
            $stmt->bind_param("ids", $cotista_id, $bonus, $data);
            $stmt->execute();
        }

        redirecionar();
    }
}

if ($tipo == "novo_cotista") {
    $nome = trim($_POST['nome'] ?? '');

    if ($nome !== '') {
        $stmt = $conn->prepare("INSERT INTO cotistas (nome) VALUES (?)");
        $stmt->bind_param("s", $nome);
        $stmt->execute();
        redirecionar();
    }
}

if ($tipo == "emprestimo") {
    $cotista_id = (int) ($_POST['cotista_id'] ?? 0);
    $valor = (float) ($_POST['valor'] ?? 0);
    $data = $_POST['data'] ?? '';
    $valor_negativo = -abs($valor);

    if ($valor > 0 && !empty($data)) {
        $stmt = $conn->prepare("INSERT INTO movimentacoes (cotista_id, tipo, valor, data) VALUES (?, 'emprestimo', ?, ?)");
        $stmt->bind_param("ids", $cotista_id, $valor_negativo, $data);
        $stmt->execute();
        redirecionar();
    }
}

if ($tipo == "pagamento_emprestimo") {
    $cotista_id = (int) ($_POST['cotista_id'] ?? 0);
    $juros = (float) ($_POST['juros'] ?? 0);
    $abatimento = (float) ($_POST['abatimento'] ?? 0);
    $data = $_POST['data'] ?? '';

    if (!empty($data)) {
        if ($juros > 0) {
            $stmt = $conn->prepare("INSERT INTO movimentacoes (cotista_id, tipo, valor, data) VALUES (?, 'juros', ?, ?)");
            $stmt->bind_param("ids", $cotista_id, $juros, $data);
            $stmt->execute();
        }

        if ($abatimento > 0) {
            $stmt = $conn->prepare("INSERT INTO movimentacoes (cotista_id, tipo, valor, data) VALUES (?, 'abatimento', ?, ?)");
            $stmt->bind_param("ids", $cotista_id, $abatimento, $data);
            $stmt->execute();
        }

        redirecionar();
    }
}

if ($tipo == "excluir_cotista") {
    $cotista_id = (int) ($_POST['cotista_id'] ?? 0);

    $stmt = $conn->prepare("DELETE FROM movimentacoes WHERE cotista_id = ?");
    $stmt->bind_param("i", $cotista_id);
    $stmt->execute();

    $stmt = $conn->prepare("DELETE FROM cotistas WHERE id = ?");
    $stmt->bind_param("i", $cotista_id);
    $stmt->execute();

    redirecionar();
}

/* ===============================
   CONSULTAS
   =============================== */

/*
Saldo automático da caixinha:
- cota soma
- bonus_cota soma
- juros soma
- abatimento soma
- pagamento_atraso soma
- emprestimo subtrai (já está negativo)
- valor_atraso NÃO soma
*/
$total = $conn->query("
    SELECT 
        COALESCE(SUM(
            CASE
                WHEN tipo = 'valor_atraso' THEN 0
                ELSE valor
            END
        ), 0) AS total
    FROM movimentacoes
")->fetch_assoc();

$cotistas = $conn->query("SELECT * FROM cotistas ORDER BY nome ASC");

$historico = $conn->query("
    SELECT m.*, c.nome 
    FROM movimentacoes m
    LEFT JOIN cotistas c ON m.cotista_id = c.id
    ORDER BY m.data DESC, m.id DESC
");
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Caixinha</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <header>
        Sistema da Caixinha 💰
    </header>

    <div class="container">

        <div class="dashboard">
            <div class="card">
                <h3>Total da Caixinha</h3>
                <div class="valor">
                    R$ <?= number_format($total['total'] ?? 0, 2, ',', '.') ?>
                </div>
            </div>
        </div>




        <div class="form-section">
            <h2>Pagamento de Cota</h2>
            <form method="POST">
                <input type="hidden" name="tipo" value="pagamento_cota">

                <select name="cotista_id" required>
                    <option value="">Selecione</option>
                    <?php
                    $cotistas->data_seek(0);
                    while ($c = $cotistas->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['nome']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <input type="number" step="0.01" name="valor" placeholder="Valor" required>
                <input type="number" step="0.01" name="bonus" placeholder="Juros cota (opcional)">
                <input type="date" name="data" required>
                <button type="submit">Registrar</button>
            </form>
        </div>

        <div class="form-section">
            <h2>Empréstimo</h2>
            <form method="POST">
                <input type="hidden" name="tipo" value="emprestimo">

                <select name="cotista_id" required>
                    <option value="">Selecione</option>
                    <?php
                    $cotistas->data_seek(0);
                    while ($c = $cotistas->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['nome']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <input type="number" step="0.01" name="valor" placeholder="Valor" required>
                <input type="date" name="data" required>
                <button type="submit">Conceder</button>
                <br>

            </form>
            <h2>Pagamento de Empréstimo</h2>
            <form method="POST">
                <input type="hidden" name="tipo" value="pagamento_emprestimo">

                <select name="cotista_id" required>
                    <option value="">Selecione</option>
                    <?php
                    $cotistas->data_seek(0);
                    while ($c = $cotistas->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['nome']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <input type="number" step="0.01" name="juros" placeholder="Juros pagos" required>
                <input type="number" step="0.01" name="abatimento" placeholder="Abatimento" required>
                <input type="date" name="data" required>
                <button type="submit">Registrar</button>
            </form>
        </div>


        <div class="form-section">
            <h2>Valores em Atraso</h2>
            <form method="POST">
                <input type="hidden" name="tipo" value="valor_atraso">

                <select name="cotista_id" required>
                    <option value="">Selecione</option>
                    <?php
                    $cotistas->data_seek(0);
                    while ($c = $cotistas->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['nome']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <input type="number" step="0.01" name="valor" placeholder="Valor em atraso" required>
                <input type="date" name="data" required>
                <button type="submit">Registrar</button>
                <br>

            </form>
            <h2>Pagamento de Atraso</h2>
            <form method="POST">
                <input type="hidden" name="tipo" value="pagamento_atraso">

                <select name="cotista_id" required>
                    <option value="">Selecione</option>
                    <?php
                    $cotistas->data_seek(0);
                    while ($c = $cotistas->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['nome']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <input type="number" step="0.01" name="valor" placeholder="Valor pago do atraso" required>
                <input type="date" name="data" required>
                <button type="submit">Registrar Pagamento</button>
            </form>
        </div>


        <div class="form-section">
            <h2>Cadastrar Cotista</h2>
            <form method="POST">
                <input type="hidden" name="tipo" value="novo_cotista">
                <input type="text" name="nome" placeholder="Nome do cotista" required>
                <button type="submit">Cadastrar</button>
            </form>
        </div>

        <div class="form-section">
            <h2>Resumo dos Cotistas</h2>

            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Investido</th>
                        <th>Juros Cota</th>
                        <th>Emprestado</th>
                        <th>Abatido</th>
                        <th>Juros Pagos</th>
                        <th>Valores em Atraso</th>
                        <th>Dívida Atual</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>

                    <?php
                    $listaCotistas = $conn->query("SELECT * FROM cotistas ORDER BY nome ASC");

                    while ($cot = $listaCotistas->fetch_assoc()) {
                        $id = (int) $cot['id'];

                        $res = $conn->query("
                            SELECT 
                                COALESCE(SUM(CASE WHEN tipo='cota' THEN valor ELSE 0 END), 0) as investido,
                                COALESCE(SUM(CASE WHEN tipo='bonus_cota' THEN valor ELSE 0 END), 0) as bonus_cota,
                                COALESCE(SUM(CASE WHEN tipo='emprestimo' THEN valor ELSE 0 END), 0) as emprestado,
                                COALESCE(SUM(CASE WHEN tipo='valor_atraso' THEN valor ELSE 0 END), 0) as atraso,
                                COALESCE(SUM(CASE WHEN tipo='pagamento_atraso' THEN valor ELSE 0 END), 0) as atraso_pago,
                                COALESCE(SUM(CASE WHEN tipo='abatimento' THEN valor ELSE 0 END), 0) as abatido,
                                COALESCE(SUM(CASE WHEN tipo='juros' THEN valor ELSE 0 END), 0) as juros
                            FROM movimentacoes
                            WHERE cotista_id = $id
                        ");

                        $dados = $res->fetch_assoc();

                        $investido = (float) ($dados['investido'] ?? 0);
                        $bonus_cota = (float) ($dados['bonus_cota'] ?? 0);
                        $emprestado = abs((float) ($dados['emprestado'] ?? 0));
                        $abatido = (float) ($dados['abatido'] ?? 0);
                        $juros = (float) ($dados['juros'] ?? 0);
                        $atraso = (float) ($dados['atraso'] ?? 0);
                        $atraso_pago = (float) ($dados['atraso_pago'] ?? 0);

                        $atraso_pendente = $atraso - $atraso_pago;
                        if ($atraso_pendente < 0) {
                            $atraso_pendente = 0;
                        }

                        $divida = ($emprestado + $atraso) - ($abatido + $atraso_pago);
                        ?>

                        <tr>
                            <td><?= htmlspecialchars($cot['nome']) ?></td>
                            <td>R$ <?= number_format($investido, 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($bonus_cota, 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($emprestado, 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($abatido, 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($juros, 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($atraso_pendente, 2, ',', '.') ?></td>
                            <td style="color: <?= $divida > 0 ? 'red' : 'green' ?>;">
                                R$ <?= number_format($divida, 2, ',', '.') ?>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir?');">
                                    <input type="hidden" name="tipo" value="excluir_cotista">
                                    <input type="hidden" name="cotista_id" value="<?= $cot['id'] ?>">
                                    <button type="submit" style="background:red;">Excluir</button>
                                </form>
                            </td>
                        </tr>

                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div class="form-section">
            <h2>Relatório em PDF</h2>

            <form action="exportar.php" method="post" style="margin-bottom: 15px;">
                <input type="hidden" name="tipo_relatorio" value="caixinha_tudo">
                <button type="submit">Exportar PDF - Caixinha Completa</button>
            </form>

            <form action="exportar.php" method="post" style="margin-bottom: 15px;">
                <label>Caixinha por mês:</label>
                <input type="month" name="mes" required>
                <input type="hidden" name="tipo_relatorio" value="caixinha_mes">
                <button type="submit">Exportar PDF</button>
            </form>

            <form action="exportar.php" method="post" style="margin-bottom: 15px;">
                <label>Caixinha por período:</label>
                <input type="date" name="data_inicio" required>
                <input type="date" name="data_fim" required>
                <input type="hidden" name="tipo_relatorio" value="caixinha_periodo">
                <button type="submit">Exportar PDF</button>
            </form>

            <form action="exportar.php" method="post" style="margin-bottom: 15px;">
                <label>Cotista completo:</label>
                <select name="cotista_id" required>
                    <option value="">Selecione o cotista</option>
                    <?php
                    $cotistas->data_seek(0);
                    while ($c = $cotistas->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['nome']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <input type="hidden" name="tipo_relatorio" value="cotista_tudo">
                <button type="submit">Exportar PDF</button>
            </form>

            <form action="exportar.php" method="post" style="margin-bottom: 15px;">
                <label>Cotista por mês:</label>
                <select name="cotista_id" required>
                    <option value="">Selecione o cotista</option>
                    <?php
                    $cotistas->data_seek(0);
                    while ($c = $cotistas->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['nome']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <input type="month" name="mes" required>
                <input type="hidden" name="tipo_relatorio" value="cotista_mes">
                <button type="submit">Exportar PDF</button>
            </form>

            <form action="exportar.php" method="post" style="margin-bottom: 15px;">
                <label>Cotista por período:</label>
                <select name="cotista_id" required>
                    <option value="">Selecione o cotista</option>
                    <?php
                    $cotistas->data_seek(0);
                    while ($c = $cotistas->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['nome']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <input type="date" name="data_inicio" required>
                <input type="date" name="data_fim" required>
                <input type="hidden" name="tipo_relatorio" value="cotista_periodo">
                <button type="submit">Exportar PDF</button>
            </form>

            <table>

                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Cotista</th>
                        <th>Tipo</th>
                        <th>Valor</th>
                      
                    </tr>
                </thead>
                <tbody>
                    <?php while ($h = $historico->fetch_assoc()): ?>
                        <tr>
                            <td><?= !empty($h['data']) ? date("d/m/Y", strtotime($h['data'])) : '-' ?></td>
                            <td><?= htmlspecialchars($h['nome'] ?? '-') ?></td>
                            <td><?= htmlspecialchars(ucfirst($h['tipo'])) ?></td>
                            <td>R$ <?= number_format($h['valor'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    </div>
</body>

</html> 

<!--Botão de apagar--> 

<!--<th><form method="POST"
onsubmit="return confirm('Tem certeza que deseja apagar todo o histórico?');">
<input type="hidden" name="tipo" value="limpar_historico">
<button type="submit" style="background:red; color:white;">
🗑️ Apagar Histórico
</button>
</form></th>-->