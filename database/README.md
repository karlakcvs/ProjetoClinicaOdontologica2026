# Banco de demonstração

`demo-data.sql` é uma exportação portátil do banco MySQL de demonstração da clínica, com pacientes,
contatos, consultas e anotações anonimizados. As contas de demonstração usam a senha
`DemoClinic#2026`; troque-a antes de incluir qualquer dado real.

Para importar a demonstração, inicie o MySQL com `docker compose up -d db` e importe este arquivo
pelo phpMyAdmin em <http://localhost:8888>. O arquivo cria/substitui o banco `barbearia`.

`mysql-data/`, `.env` e os uploads ficam locais e não devem ser publicados: o diretório do MySQL
contém arquivos internos não portáteis, e uploads podem conter dados sensíveis. O dump não contém
credenciais do Docker.
