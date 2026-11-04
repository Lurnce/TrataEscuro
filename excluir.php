<?php
require_once __DIR__ . '/conexao.php';
exigir_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('index.php');
}
csrf_validar();

$id = (int)campo('id');

try {
    $pdo->beginTransaction();
    // Remove as avaliações do livro e depois o próprio livro
    $pdo->prepare("DELETE FROM avaliacoes WHERE livro_id = :id")->execute([':id' => $id]);
    $consulta = $pdo->prepare("DELETE FROM livros WHERE id = :id");
    $consulta->execute([':id' => $id]);
    $removidos = $consulta->rowCount();
    $pdo->commit();

    flash($removidos > 0 ? 'Livro excluído.' : 'Livro não encontrado.', $removidos > 0 ? 'ok' : 'erro');
} catch (PDOException $erro) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    if ($erro->getCode() === '23000') { // violação de chave estrangeira
        flash('Este livro tem empréstimos registrados e não pode ser excluído.', 'erro');
    } else {
        error_log('Trata: erro ao excluir livro - ' . $erro->getMessage());
        flash('Não foi possível excluir o livro. Tente novamente.', 'erro');
    }
}

redirecionar('index.php');
