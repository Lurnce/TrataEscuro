<?php
require_once __DIR__ . '/conexao.php';
exigir_admin();

$erros = [];
$nome  = '';
$turma = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = campo('acao');

    if ($acao === 'excluir') {
        try {
            $c = $pdo->prepare("DELETE FROM alunos WHERE id = :id");
            $c->execute([':id' => (int)campo('aluno_id')]);
            flash('Aluno excluído.');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                flash('Este aluno tem empréstimos registrados e não pode ser excluído.', 'erro');
            } else {
                error_log('Trata: erro ao excluir aluno - ' . $ex->getMessage());
                flash('Não foi possível excluir o aluno.', 'erro');
            }
        }
        redirecionar('alunos.php');
    }

    if ($acao === 'vincular') {
        // Liga (ou desliga, com usuario_id = 0) a conta de um leitor ao cadastro do aluno
        $aluno_id   = (int)campo('aluno_id');
        $usuario_id = (int)campo('usuario_id');
        try {
            $pdo->beginTransaction();
            if ($usuario_id > 0) {
                $c = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id = :id");
                $c->execute([':id' => $usuario_id]);
                if ((int)$c->fetchColumn() === 0) throw new RuntimeException('Conta não encontrada.');
                // Uma conta só pode estar ligada a um aluno
                $pdo->prepare("UPDATE alunos SET usuario_id = NULL WHERE usuario_id = :u")->execute([':u' => $usuario_id]);
            }
            $c = $pdo->prepare("UPDATE alunos SET usuario_id = :u WHERE id = :a");
            $c->execute([':u' => $usuario_id > 0 ? $usuario_id : null, ':a' => $aluno_id]);
            $pdo->commit();
            flash($usuario_id > 0 ? 'Conta vinculada ao aluno.' : 'Vínculo removido.');
        } catch (RuntimeException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash($ex->getMessage(), 'erro');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Trata: erro ao vincular conta - ' . $ex->getMessage());
            flash('Não foi possível alterar o vínculo.', 'erro');
        }
        redirecionar('alunos.php');
    }

    // ---------- Novo aluno ----------
    $nome  = campo('nome');
    $turma = campo('turma');
    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 100) $erros[] = 'Informe o nome do aluno (2 a 100 caracteres).';
    if (mb_strlen($turma) > 20)                          $erros[] = 'A turma pode ter até 20 caracteres.';

    if (!$erros) {
        try {
            $c = $pdo->prepare("INSERT INTO alunos (nome, turma) VALUES (:nome, :turma)");
            $c->execute([':nome' => $nome, ':turma' => $turma]); // a coluna turma não aceita NULL
            flash('Aluno cadastrado.');
            redirecionar('alunos.php');
        } catch (PDOException $ex) {
            error_log('Trata: erro ao cadastrar aluno - ' . $ex->getMessage());
            $erros[] = 'Não foi possível cadastrar o aluno.';
        }
    }
}

$alunos = $pdo->query(
    "SELECT a.id, a.nome, a.turma, a.usuario_id, u.nome AS conta_nome,
            (SELECT COUNT(*) FROM emprestimos e WHERE e.aluno_id = a.id AND e.data_devolucao IS NULL) AS abertos,
            (SELECT COUNT(*) FROM emprestimos e WHERE e.aluno_id = a.id) AS total
     FROM alunos a LEFT JOIN usuarios u ON u.id = a.usuario_id
     ORDER BY a.nome"
)->fetchAll();

// Contas que ainda não estão ligadas a nenhum aluno
$contas_livres = $pdo->query(
    "SELECT id, nome, email FROM usuarios
     WHERE id NOT IN (SELECT usuario_id FROM alunos WHERE usuario_id IS NOT NULL)
     ORDER BY nome"
)->fetchAll();

$titulo_pagina = 'Alunos';
require_once __DIR__ . '/topo.php';
?>

<div class="page-head">
  <div>
    <h1>Alunos</h1>
    <p class="sub">Cadastre quem pode retirar livros e ligue cada aluno à conta de leitor.</p>
  </div>
</div>

<?php if ($erros): ?>
  <div class="alerta alerta-erro"><ul><?php foreach ($erros as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
  <h2>Novo aluno</h2>
  <form method="post" class="linha">
    <?= csrf_campo() ?>
    <input name="nome" value="<?= e($nome) ?>" placeholder="Nome completo" required maxlength="100" aria-label="Nome">
    <input name="turma" value="<?= e($turma) ?>" placeholder="Turma (ex.: 7º A)" maxlength="20" aria-label="Turma">
    <button class="btn btn-primario">Cadastrar</button>
  </form>
</div>

<h2 class="titulo-secao">Alunos cadastrados (<?= count($alunos) ?>)</h2>
<?php if (!$alunos): ?>
  <div class="vazio">Nenhum aluno cadastrado.</div>
<?php else: ?>
<div class="tabela-wrap">
  <table>
    <thead>
      <tr><th>Nome</th><th>Turma</th><th>Em aberto</th><th>Total</th><th>Conta de leitor</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($alunos as $a): ?>
      <tr>
        <td><?= e($a['nome']) ?></td>
        <td><?= e($a['turma']) ?></td>
        <td><?= (int)$a['abertos'] ?></td>
        <td><?= (int)$a['total'] ?></td>
        <td>
          <?php if ($a['usuario_id']): ?>
            <form method="post" class="acoes">
              <?= csrf_campo() ?>
              <input type="hidden" name="acao" value="vincular">
              <input type="hidden" name="aluno_id" value="<?= (int)$a['id'] ?>">
              <input type="hidden" name="usuario_id" value="0">
              <span class="badge badge-ok"><?= e($a['conta_nome']) ?></span>
              <button class="btn btn-sm btn-fantasma">Desvincular</button>
            </form>
          <?php elseif ($contas_livres): ?>
            <form method="post" class="acoes">
              <?= csrf_campo() ?>
              <input type="hidden" name="acao" value="vincular">
              <input type="hidden" name="aluno_id" value="<?= (int)$a['id'] ?>">
              <select name="usuario_id" required aria-label="Conta de leitor">
                <option value="">Escolher conta…</option>
                <?php foreach ($contas_livres as $cl): ?>
                  <option value="<?= (int)$cl['id'] ?>"><?= e($cl['nome']) ?> (<?= e($cl['email']) ?>)</option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-fantasma">Vincular</button>
            </form>
          <?php else: ?>
            <span class="mudo">Sem contas livres</span>
          <?php endif; ?>
        </td>
        <td>
          <form method="post" onsubmit="return confirm('Excluir este aluno?')">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="excluir">
            <input type="hidden" name="aluno_id" value="<?= (int)$a['id'] ?>">
            <button class="btn btn-sm btn-perigo">Excluir</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/rodape.php'; ?>
