# Trata

Sistema de biblioteca escolar em PHP + MariaDB (XAMPP): acervo, alunos, empréstimos, login, perfil editável, avaliações e recomendações de livros.

Este pacote vem em **dois visuais** (mesma lógica, aparência diferente):

| Pasta | Visual |
|---|---|
| `trata-claro` | Cabeçalho no topo, fundo claro, vitrine de capas de livros |
| `trata-escuro` | Menu lateral, tema escuro, livros em lista |

Use **um** dos dois. Os dados do banco são os mesmos, então dá para trocar de visual quando quiser.

## Como instalar

1. Copie a pasta escolhida para `C:\xampp\htdocs\trata` (ou equivalente).
2. Inicie **Apache** e **MySQL/MariaDB** no XAMPP.
3. No phpMyAdmin, abra a aba **SQL** e execute o conteúdo de `banco.sql`
   (só cria o que ainda não existe e não apaga dados; é seguro rodar sobre o banco atual).
4. Se necessário, ajuste usuário/senha do banco em `conexao.php`.
5. Acesse `http://localhost/trata/` e crie sua conta.
   **A primeira conta criada vira bibliotecário (admin)**; as seguintes são leitores.

## Perfis

| Perfil | Pode |
|---|---|
| Bibliotecário (admin) | Tudo: livros, alunos, empréstimos, moderar avaliações |
| Leitor | Ver o acervo, avaliar livros, editar o próprio perfil e receber sugestões |

Para promover alguém a bibliotecário:

```sql
UPDATE usuarios SET perfil = 'admin' WHERE email = 'email@exemplo.com';
```

## Perfil do leitor

Em **Meu perfil** o leitor altera nome, e-mail, uma frase sobre si, o gênero favorito e a senha,
e vê as próprias avaliações e empréstimos.

## Recomendações

A seção **Para você** (acervo e perfil) sugere livros que o leitor ainda não avaliou nem pegou emprestado, combinando:

- as notas que ele deu (4 e 5 estrelas puxam livros do mesmo gênero e autor; 1 e 2 afastam);
- os empréstimos do aluno ligado à conta dele;
- o gênero favorito do perfil;
- a nota média dos leitores (suavizada quando há poucas avaliações) e quantas vezes o livro foi emprestado.

**Importante:** os empréstimos são registrados para *alunos*, e o login é de *usuários*. Para os empréstimos
entrarem nas recomendações, o bibliotecário liga cada conta ao seu aluno em **Alunos > Conta de leitor > Vincular**.
Sem o vínculo, as sugestões usam apenas notas, gênero favorito e popularidade.

## Estrutura

- `conexao.php`: banco, sessão, login, CSRF e funções auxiliares
- `recomendacoes.php`: motor de sugestões
- `componentes.php`: cartão de livro (muda conforme o visual)
- `topo.php` / `rodape.php`: cabeçalho e rodapé (mudam conforme o visual)
- `login.php`, `cadastro.php`, `sair.php`, `perfil.php`: conta do usuário
- `index.php`, `livro.php`, `novo.php`, `editar.php`, `excluir.php`, `form_livro.php`: acervo e avaliações
- `emprestimos.php`, `alunos.php`: gestão da biblioteca
- `estilo.css`: visual
- `banco.sql`: estrutura do banco
