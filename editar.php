<?php
require_once __DIR__ . '/conexao.php';
exigir_admin();

$id = (int)($_GET['id'] ?? 0);

$consulta = $pdo->prepare("SELECT * FROM livros WHERE id = :id");
$consulta->execute([':id' => $id]);
$livro = $consulta->fetch();
if (!$livro) {
    flash('Livro não encontrado.', 'erro');
    redirecionar('index.php');
}

$d = [
    'titulo'     => (string)$livro['titulo'],
    'autor'      => (string)$livro['autor'],
    'ano'        => (string)($livro['ano'] ?? ''),
    'genero'     => (string)($livro['genero'] ?? ''),
    'quantidade' => (string)$livro['quantidade'],
];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    [$d, $erros] = validar_livro();

    // Não permite reduzir a quantidade abaixo do número de exemplares emprestados
    if (!$erros) {
        $c = $pdo->prepare("SELECT COUNT(*) FROM emprestimos WHERE livro_id = :id AND data_devolucao IS NULL");
        $c->execute([':id' => $id]);
        $emprestados = (int)$c->fetchColumn();
        if ((int)$d['quantidade'] < $emprestados) {
            $erros[] = "Há $emprestados exemplar(es) emprestado(s) no momento; a quantidade não pode ser menor que isso.";
        }
    }

    if (!$erros) {
        $sql = "UPDATE livros
                SET titulo = :titulo, autor = :autor, ano = :ano, genero = :genero, quantidade = :quantidade
                WHERE id = :id";
        $consulta = $pdo->prepare($sql);
        $consulta->execute([
            ':titulo'     => $d['titulo'],
            ':autor'      => $d['autor'],
            ':ano'        => $d['ano'] !== '' ? (int)$d['ano'] : null,
            ':genero'     => $d['genero'] !== '' ? $d['genero'] : null,
            ':quantidade' => (int)$d['quantidade'],
            ':id'         => $id,
        ]);
        flash('Livro atualizado!');
        redirecionar('livro.php?id=' . $id);
    }
}

$titulo_pagina = 'Editar livro';
$rotulo_botao  = 'Salvar alterações';
require_once __DIR__ . '/topo.php';
?>

<div class="page-head">
  <div>
    <h1>Editar livro</h1>
    <p class="sub"><?= e($livro['titulo']) ?></p>
  </div>
</div>

<?php require __DIR__ . '/form_livro.php'; ?>

<?php require_once __DIR__ . '/rodape.php'; ?>
