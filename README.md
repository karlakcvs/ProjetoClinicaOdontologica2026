# Projeto Clínica Odontológica 2026

Sistema de agendamento e gestão para uma clínica odontológica, desenvolvido em PHP, MySQL e Docker.

## Executar localmente

1. Copie `.env.example` para `.env` e configure suas próprias credenciais.
2. Com o Docker Desktop aberto, execute:

   ```sh
   docker compose up -d --build
   ```

3. Acesse <http://localhost>.

Para instruções detalhadas, consulte [LEIA-ME.md](LEIA-ME.md). A análise de segurança está em [SEGURANCA.md](SEGURANCA.md).

## Dados locais

Não publique o arquivo `.env`, o diretório `mysql-data/` nem arquivos enviados em `src/uploads/`. Eles são locais e podem conter credenciais, dados de pacientes ou informações de saúde. Configure senhas próprias antes de usar o sistema.
