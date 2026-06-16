<?php
require_once "conexao.php";

$tipo = $_POST['tipo'] ?? '';

if ($tipo == "novo_cotista") {

    $nome = $_POST['nome'];

    $stmt = $conn->prepare("INSERT INTO cotistas (nome) VALUES (?)");
    $stmt->bind_param("s", $nome);
    $stmt->execute();

}


/* ===============================
   PAGAMENTO DE COTA (INVESTIMENTO)
   =============================== */
if ($tipo == "pagamento_cota") {

    $cotista_id = $_POST['cotista_id'];
    $valor = $_POST['valor'];

    $stmt = $conn->prepare("INSERT INTO movimentacoes (cotista_id, tipo, valor) VALUES (?, 'cota', ?)");
    $stmt->bind_param("id", $cotista_id, $valor);
    $stmt->execute();

}


/* ===============================
   EMPRÉSTIMO (SAÍDA DA CAIXINHA)
   =============================== */
if ($tipo == "emprestimo") {

    $cotista_id = $_POST['cotista_id'];
    $valor = $_POST['valor'];

    // registra como saída (valor negativo)
    $valor_negativo = -abs($valor);

    $stmt = $conn->prepare("INSERT INTO movimentacoes (cotista_id, tipo, valor) VALUES (?, 'emprestimo', ?)");
    $stmt->bind_param("id", $cotista_id, $valor_negativo);
    $stmt->execute();
}


/* ===================================
   PAGAMENTO DE EMPRÉSTIMO
   SEPARANDO JUROS E ABATIMENTO
   =================================== */
if ($tipo == "pagamento_emprestimo") {

    $cotista_id = $_POST['cotista_id'];
    $juros = $_POST['juros'];
    $abatimento = $_POST['abatimento'];

    // JUROS → lucro da caixinha
    if ($juros > 0) {
        $stmt = $conn->prepare("INSERT INTO movimentacoes (cotista_id, tipo, valor) VALUES (?, 'juros', ?)");
        $stmt->bind_param("id", $cotista_id, $juros);
        $stmt->execute();
    }

    // ABATIMENTO → devolução do empréstimo
    if ($abatimento > 0) {
        $stmt = $conn->prepare("INSERT INTO movimentacoes (cotista_id, tipo, valor) VALUES (?, 'abatimento', ?)");
        $stmt->bind_param("id", $cotista_id, $abatimento);
        $stmt->execute();
    }
}

header("Location: index.php");
exit();
?>