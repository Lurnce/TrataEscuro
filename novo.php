<?php
// =====================================================
// novo.php: cadastra um livro (somente bibliotecário)
// =====================================================
require_once __DIR__ . '/conexao.php';
exigir_admin();

$d     = ['titulo' => '', 'autor' => '', 'ano' => '', 'genero' => '', 'quantidade' => '1'];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    [$d, $erros] = validar_livro();

    if (!$erros) {
        $sql = "INSERT INTO livros (titulo, autor, ano, genero, quantidade)
                VALUES (:titulo, :autor, :ano, :genero, :quantidade)";
        $consulta = $pdo->prepare($sql);
        $consulta->execute([
            ':titulo'     => $d['titulo'],
            ':autor'      => $d['autor'],
            ':ano'        => $d['ano'] !== '' ? (int)$d['ano'] : null,
            ':genero'     => $d['genero'] !== '' ? $d['genero'] : null,
            ':quantidade' => (int)$d['quantidade'],
        ]);
        flash('Livro cadastrado!');
        redirecionar('index.php');
    }
}

$titulo_pagina = 'Novo livro';
$rotulo_botao  = 'Cadastrar livro';
require_once __DIR__ . '/topo.php';
?>

<div class="page-head">
  <div>
    <h1>Novo livro</h1>
    <p class="sub">Cadastre um novo título no acervo.</p>
  </div>
</div>

<?php require __DIR__ . '/form_livro.php'; ?>

<?php require_once __DIR__ . '/rodape.php'; ?>
