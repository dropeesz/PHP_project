<?php
require_once "conexao.php";
require_once "dompdf/autoload.inc.php";

use Dompdf\Dompdf;
use Dompdf\Options;

$tipo_relatorio = $_POST['tipo_relatorio'] ?? '';

$sql = "
    SELECT m.data, c.nome, m.tipo, m.valor
    FROM movimentacoes m
    LEFT JOIN cotistas c ON m.cotista_id = c.id
";

$where = [];
$params = [];
$types = "";

$titulo = "Relatório";
$subtitulo = "";
$nomeArquivo = "relatorio.pdf";

/* ============================
   CAIXINHA TOTAL
   ============================ */
if ($tipo_relatorio == "caixinha_tudo") {
    $titulo = "Relatório Completo da Caixinha";
    $subtitulo = "Período: Todos os registros";
    $nomeArquivo = "caixinha_completa.pdf";
}

/* ============================
   CAIXINHA POR MÊS
   ============================ */
if ($tipo_relatorio == "caixinha_mes") {
    $mes = $_POST['mes'] ?? '';

    if (!empty($mes)) {
        $where[] = "DATE_FORMAT(m.data, '%Y-%m') = ?";
        $params[] = $mes;
        $types .= "s";

        $titulo = "Relatório da Caixinha";
        $subtitulo = "Mês selecionado: " . $mes;
        $nomeArquivo = "caixinha_mes_" . $mes . ".pdf";
    }
}

/* ============================
   CAIXINHA POR PERÍODO
   ============================ */
if ($tipo_relatorio == "caixinha_periodo") {
    $data_inicio = $_POST['data_inicio'] ?? '';
    $data_fim = $_POST['data_fim'] ?? '';

    if (!empty($data_inicio) && !empty($data_fim)) {
        $where[] = "DATE(m.data) BETWEEN ? AND ?";
        $params[] = $data_inicio;
        $params[] = $data_fim;
        $types .= "ss";

        $titulo = "Relatório da Caixinha por Período";
        $subtitulo = "Período: {$data_inicio} até {$data_fim}";
        $nomeArquivo = "caixinha_periodo_" . $data_inicio . "_ate_" . $data_fim . ".pdf";
    }
}

/* ============================
   COTISTA TOTAL
   ============================ */
if ($tipo_relatorio == "cotista_tudo") {
    $cotista_id = $_POST['cotista_id'] ?? '';

    if (!empty($cotista_id)) {
        $where[] = "m.cotista_id = ?";
        $params[] = $cotista_id;
        $types .= "i";

        $stmtNome = $conn->prepare("SELECT nome FROM cotistas WHERE id = ?");
        $stmtNome->bind_param("i", $cotista_id);
        $stmtNome->execute();
        $resNome = $stmtNome->get_result();
        $cotista = $resNome->fetch_assoc();
        $nomeCotista = $cotista['nome'] ?? 'Cotista';

        $titulo = "Relatório do Cotista";
        $subtitulo = "Cotista: " . $nomeCotista;
        $nomeArquivo = "cotista_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $nomeCotista) . ".pdf";
    }
}

/* ============================
   COTISTA POR MÊS
   ============================ */
if ($tipo_relatorio == "cotista_mes") {
    $cotista_id = $_POST['cotista_id'] ?? '';
    $mes = $_POST['mes'] ?? '';

    if (!empty($cotista_id) && !empty($mes)) {
        $where[] = "m.cotista_id = ?";
        $where[] = "DATE_FORMAT(m.data, '%Y-%m') = ?";
        $params[] = $cotista_id;
        $params[] = $mes;
        $types .= "is";

        $stmtNome = $conn->prepare("SELECT nome FROM cotistas WHERE id = ?");
        $stmtNome->bind_param("i", $cotista_id);
        $stmtNome->execute();
        $resNome = $stmtNome->get_result();
        $cotista = $resNome->fetch_assoc();
        $nomeCotista = $cotista['nome'] ?? 'Cotista';

        $titulo = "Relatório do Cotista por Mês";
        $subtitulo = "Cotista: {$nomeCotista} | Mês: {$mes}";
        $nomeArquivo = "cotista_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $nomeCotista) . "_mes_" . $mes . ".pdf";
    }
}

/* ============================
   COTISTA POR PERÍODO
   ============================ */
if ($tipo_relatorio == "cotista_periodo") {
    $cotista_id = $_POST['cotista_id'] ?? '';
    $data_inicio = $_POST['data_inicio'] ?? '';
    $data_fim = $_POST['data_fim'] ?? '';

    if (!empty($cotista_id) && !empty($data_inicio) && !empty($data_fim)) {
        $where[] = "m.cotista_id = ?";
        $where[] = "DATE(m.data) BETWEEN ? AND ?";
        $params[] = $cotista_id;
        $params[] = $data_inicio;
        $params[] = $data_fim;
        $types .= "iss";

        $stmtNome = $conn->prepare("SELECT nome FROM cotistas WHERE id = ?");
        $stmtNome->bind_param("i", $cotista_id);
        $stmtNome->execute();
        $resNome = $stmtNome->get_result();
        $cotista = $resNome->fetch_assoc();
        $nomeCotista = $cotista['nome'] ?? 'Cotista';

        $titulo = "Relatório do Cotista por Período";
        $subtitulo = "Cotista: {$nomeCotista} | Período: {$data_inicio} até {$data_fim}";
        $nomeArquivo = "cotista_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $nomeCotista) . "_periodo_" . $data_inicio . "_ate_" . $data_fim . ".pdf";
    }
}

/* ============================
   SQL FINAL
   ============================ */
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY m.data DESC, m.id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

/* ============================
   TOTAIS DO RESUMO
   ============================ */
$total_caixa = 0;
$total_investido = 0;
$total_bonus_cota = 0;
$total_emprestado = 0;
$total_abatido = 0;
$total_juros = 0;
$total_atraso = 0;
$total_pagamento_atraso = 0;

$linhas = "";

$tiposFormatados = [
    'cota' => 'Cota',
    'bonus_cota' => 'Juros da Cota',
    'emprestimo' => 'Empréstimo',
    'abatimento' => 'Abatimento',
    'juros' => 'Juros Pagos',
    'valor_atraso' => 'Valor em Atraso',
    'pagamento_atraso' => 'Pagamento de Atraso'
];

while ($row = $result->fetch_assoc()) {
    $tipo = $row['tipo'];
    $valor = (float) $row['valor'];

    if ($tipo === 'cota') {
        $total_investido += $valor;
        $total_caixa += $valor;
    }

    if ($tipo === 'bonus_cota') {
        $total_bonus_cota += $valor;
        $total_caixa += $valor;
    }

    if ($tipo === 'juros') {
        $total_juros += $valor;
        $total_caixa += $valor;
    }

    if ($tipo === 'abatimento') {
        $total_abatido += $valor;
        $total_caixa += $valor;
    }

    if ($tipo === 'emprestimo') {
        $total_emprestado += abs($valor);
        $total_caixa += $valor; // já é negativo
    }

    if ($tipo === 'valor_atraso') {
        $total_atraso += $valor;
        // NÃO soma no caixa
    }

    if ($tipo === 'pagamento_atraso') {
        $total_pagamento_atraso += $valor;
        $total_caixa += $valor;
    }

    $data = htmlspecialchars($row['data']);
    $nome = htmlspecialchars($row['nome'] ?? '-');
    $tipoFormatado = htmlspecialchars($tiposFormatados[$tipo] ?? ucfirst($tipo));
    $valorFormatado = "R$ " . number_format($valor, 2, ',', '.');

    $linhas .= "
        <tr>
            <td>{$data}</td>
            <td>{$nome}</td>
            <td>{$tipoFormatado}</td>
            <td>{$valorFormatado}</td>
        </tr>
    ";
}

$total_divida = ($total_emprestado + $total_atraso) - ($total_abatido + $total_pagamento_atraso);
if ($total_divida < 0) {
    $total_divida = 0;
}

/* ============================
   FORMATAÇÃO
   ============================ */
$totalCaixaFmt = "R$ " . number_format($total_caixa, 2, ',', '.');
$totalInvestidoFmt = "R$ " . number_format($total_investido, 2, ',', '.');
$totalBonusFmt = "R$ " . number_format($total_bonus_cota, 2, ',', '.');
$totalEmprestadoFmt = "R$ " . number_format($total_emprestado, 2, ',', '.');
$totalAbatidoFmt = "R$ " . number_format($total_abatido, 2, ',', '.');
$totalJurosFmt = "R$ " . number_format($total_juros, 2, ',', '.');
$totalAtrasoFmt = "R$ " . number_format($total_atraso, 2, ',', '.');
$totalPagamentoAtrasoFmt = "R$ " . number_format($total_pagamento_atraso, 2, ',', '.');
$totalDividaFmt = "R$ " . number_format($total_divida, 2, ',', '.');

/* ============================
   HTML PDF
   ============================ */
$html = "
<!DOCTYPE html>
<html lang='pt-br'>
<head>
<meta charset='UTF-8'>
<style>

body{
font-family: DejaVu Sans, sans-serif;
font-size:12px;
color:#222;
margin:20px;
}

h1{
margin:0;
font-size:22px;
}

.subtitulo{
margin-bottom:18px;
color:#666;
}

/* DASHBOARD */

.dashboard{
width:100%;
margin-bottom:25px;
}

.card{
width:30%;
display:inline-block;
background:#f4f4f4;
border:1px solid #ddd;
border-radius:6px;
padding:10px;
margin:4px;
}

.card-titulo{
font-size:11px;
color:#666;
}

.card-valor{
font-size:16px;
font-weight:bold;
margin-top:4px;
}

/* CORES */

.caixa{
background:#e8f7ec;
border:1px solid #b7e3c2;
}

.divida{
background:#fdeaea;
border:1px solid #f3bcbc;
}

.investido{
background:#eef3ff;
border:1px solid #cbd7ff;
}

/* TABELA */

table{
width:100%;
border-collapse:collapse;
}

th{
background:#111;
color:#fff;
padding:8px;
border:1px solid #ccc;
text-align:left;
}

td{
padding:8px;
border:1px solid #ccc;
}

.rodape{
margin-top:20px;
font-size:11px;
color:#666;
}

</style>
</head>

<body>

<h1>{$titulo}</h1>
<div class='subtitulo'>{$subtitulo}</div>

<div class='dashboard'>

<div class='card caixa'>
<div class='card-titulo'>Total em Caixa</div>
<div class='card-valor'>{$totalCaixaFmt}</div>
</div>

<div class='card investido'>
<div class='card-titulo'>Total Investido</div>
<div class='card-valor'>{$totalInvestidoFmt}</div>
</div>

<div class='card'>
<div class='card-titulo'>Juros da Cota</div>
<div class='card-valor'>{$totalBonusFmt}</div>
</div>

<div class='card'>
<div class='card-titulo'>Juros Pagos</div>
<div class='card-valor'>{$totalJurosFmt}</div>
</div>

<div class='card'>
<div class='card-titulo'>Total Emprestado</div>
<div class='card-valor'>{$totalEmprestadoFmt}</div>
</div>

<div class='card'>
<div class='card-titulo'>Total Abatido</div>
<div class='card-valor'>{$totalAbatidoFmt}</div>
</div>

<div class='card'>
<div class='card-titulo'>Total em Atraso</div>
<div class='card-valor'>{$totalAtrasoFmt}</div>
</div>

<div class='card'>
<div class='card-titulo'>Pago de Atraso</div>
<div class='card-valor'>{$totalPagamentoAtrasoFmt}</div>
</div>

<div class='card divida'>
<div class='card-titulo'>Dívida Total</div>
<div class='card-valor'>{$totalDividaFmt}</div>
</div>

</div>

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
{$linhas}
</tbody>

</table>

<div class='rodape'>
Documento gerado automaticamente pelo Sistema da Caixinha.
</div>

</body>
</html>
";

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream($nomeArquivo, ["Attachment" => true]);
exit;
?>