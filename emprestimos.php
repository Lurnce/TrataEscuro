<?php
require_once __DIR__ . '/conexao.php';
exigir_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    if (isset($_POST['devolver'])) {
        // ---------- Devolução ----------
        $consulta = $pdo->prepare("UPDATE emprestimos SET data_devolucao = :hoje
                                   WHERE id = :id AND data_devolucao IS NULL");
        $consulta->execute([':hoje' => date('Y-m-d'), ':id' => (int)campo('devolver')]);
        if ($consulta->rowCount() > 0) flash('Devolução registrada.');
        else flash('Esse empréstimo já estava devolvido.', 'erro');
    } else {
        // ---------- Novo empréstimo ----------
        $aluno = (int)campo('aluno_id');
        $livro = (int)campo('livro_id');

        if (!$aluno || !$livro) {
            flash('Selecione o aluno e o livro.', 'erro');
        } else {
            try {
                $pdo->beginTransaction();

                $c = $pdo->prepare("SELECT COUNT(*) FROM alunos WHERE id = :id");
                $c->execute([':id' => $aluno]);
                if ((int)$c->fetchColumn() === 0) throw new RuntimeException('Aluno não encontrado.');

                // Trava a linha do livro para evitar empréstimos simultâneos além do estoque
                $c = $pdo->prepare("SELECT quantidade FROM livros WHERE id = :id FOR UPDATE");
                $c->execute([':id' => $livro]);
                $quantidade = $c->fetchColumn();
                if ($quantidade === false) throw new RuntimeException('Livro não encontrado.');

                $c = $pdo->prepare("SELECT COUNT(*) FROM emprestimos WHERE livro_id = :id AND data_devolucao IS NULL");
                $c->execute([':id' => $livro]);
                if ((int)$c->fetchColumn() >= (int)$quantidade) {
                    throw new RuntimeException('Não há exemplares disponíveis deste livro.');
                }

                $c = $pdo->prepare("SELECT COUNT(*) FROM emprestimos
                                    WHERE livro_id = :livro AND aluno_id = :aluno AND data_devolucao IS NULL");
                $c->execute([':livro' => $livro, ':aluno' => $aluno]);
                if ((int)$c->fetchColumn() > 0) {
                    throw new RuntimeException('Este aluno já está com um exemplar deste livro.');
                }

                $c = $pdo->prepare("INSERT INTO emprestimos (livro_id, aluno_id, data_emprestimo)
                                    VALUES (:livro, :aluno, :hoje)");
                $c->execute([':livro' => $livro, ':aluno' => $aluno, ':hoje' => date('Y-m-d')]);

                $pdo->commit();
                flash('Empréstimo registrado.');
            } catch (RuntimeException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash($ex->getMessage(), 'erro');
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Trata: erro ao registrar empréstimo - ' . $ex->getMessage());
                flash('Não foi possível registrar o empréstimo.', 'erro');
            }
        }
    }
    redirecionar('emprestimos.php' . (parametro('ver') === 'abertos' ? '?ver=abertos' : ''));
}

$ver  = parametro('ver') === 'abertos' ? 'abertos' : 'todos';
$dias = (int)PRAZO_DIAS;

$sql = "SELECT e.id, a.nome, a.turma, l.titulo, e.data_emprestimo, e.data_devolucao
        FROM emprestimos e
        JOIN alunos a ON a.id = e.aluno_id
        JOIN livros l ON l.id = e.livro_id"
     . ($ver === 'abertos' ? " WHERE e.data_devolucao IS NULL" : "")
     . " ORDER BY e.data_devolucao IS NOT NULL, e.data_emprestimo DESC, e.id DESC LIMIT 300";
$emprestimos = $pdo->query($sql)->fetchAll();

$alunos = $pdo->query("SELECT id, nome, turma FROM alunos ORDER BY nome")->fetchAll();
$livros = $pdo->query("SELECT l.id, l.titulo,
                              l.quantidade - (SELECT COUNT(*) FROM emprestimos e
                                               WHERE e.livro_id = l.id AND e.data_devolucao IS NULL) AS disp
                       FROM livros l ORDER BY l.titulo")->fetchAll();

$hoje = date('Y-m-d');
$titulo_pagina = 'Empréstimos';
require_once __DIR__ . '/topo.php';
?>

<div class="page-head">
  <div>
    <h1>Empréstimos</h1>
    <p class="sub">Registre retiradas e devoluções. Prazo padrão: <?= $dias ?> dias.</p>
  </div>
</div>

<div class="card">
  <h2>Novo empréstimo</h2>
  <?php if (!$alunos): ?>
    <p class="mudo">Cadastre ao menos um aluno em <a href="alunos.php">Alunos</a> para começar.</p>
  <?php else: ?>
  <form method="post" class="linha">
    <?= csrf_campo() ?>
    <select name="aluno_id" required aria-label="Aluno">
      <option value="">Aluno…</option>
      <?php foreach ($alunos as $a): ?>
        <option value="<?= (int)$a['id'] ?>"><?= e($a['nome']) ?><?= $a['turma'] ? ' (' . e($a['turma']) . ')' : '' ?></option>
      <?php endforeach; ?>
    </select>
    <select name="livro_id" required aria-label="Livro">
      <option value="">Livro…</option>
      <?php foreach ($livros as $l): ?>
        <option value="<?= (int)$l['id'] ?>" <?= (int)$l['disp'] <= 0 ? 'disabled' : '' ?>>
          <?= e($l['titulo']) ?> <?= (int)$l['disp'] <= 0 ? '(indisponível)' : '(' . (int)$l['disp'] . ' disp.)' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primario">Emprestar</button>
  </form>
  <?php endif; ?>
</div>

<div class="titulo-secao-linha">
  <h2>Empréstimos registrados</h2>
  <div class="abas">
    <a href="emprestimos.php" class="<?= $ver === 'todos' ? 'ativo' : '' ?>">Todos</a>
    <a href="emprestimos.php?ver=abertos" class="<?= $ver === 'abertos' ? 'ativo' : '' ?>">Em aberto</a>
  </div>
</div>

<?php if (!$emprestimos): ?>
  <div class="vazio">Nenhum empréstimo para exibir.</div>
<?php else: ?>
<div class="tabela-wrap">
  <table>
    <thead>
      <tr>
        <th>Aluno</th>
        <th>Turma</th>
        <th>Livro</th>
        <th>Retirado em</th>
        <th>Prazo</th>
        <th>Situação</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($emprestimos as $emp):
        $aberto   = $emp['data_devolucao'] === null;
        $prazo    = date('Y-m-d', strtotime($emp['data_emprestimo'] . ' +' . $dias . ' days'));
        $atrasado = $aberto && $prazo < $hoje;
      ?>
      <tr>
        <td><?= e($emp['nome']) ?></td>
        <td><?= e($emp['turma']) ?></td>
        <td><?= e($emp['titulo']) ?></td>
        <td><?= e(data_br($emp['data_emprestimo'])) ?></td>
        <td><?= e(data_br($prazo)) ?></td>
        <td>
          <?php if ($aberto): ?>
            <div class="acoes">
              <span class="badge <?= $atrasado ? 'badge-erro' : 'badge-aviso' ?>"><?= $atrasado ? 'Atrasado' : 'Em andamento' ?></span>
              <form method="post">
                <?= csrf_campo() ?>
                <button class="btn btn-sm btn-fantasma" name="devolver" value="<?= (int)$emp['id'] ?>">Registrar devolução</button>
              </form>
            </div>
          <?php else: ?>
            <span class="badge badge-ok">Devolvido em <?= e(data_br($emp['data_devolucao'])) ?></span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/rodape.php'; ?>
