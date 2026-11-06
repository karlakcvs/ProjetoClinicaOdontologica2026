# Dockerfile - Clínica Garcia Di Mase (SanyTech)
FROM php:8.2-apache

# Extensão do MySQL para o PDO
RUN docker-php-ext-install pdo_mysql

# Módulos do Apache: rewrite (URLs) e headers (cabeçalhos de segurança)
RUN a2enmod rewrite headers

# Permite que os arquivos .htaccess do projeto funcionem (proteção de pastas)
RUN sed -ri '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Segurança do Apache: esconde versão e bloqueia pastas internas mesmo sem .htaccess
RUN { \
      echo "ServerTokens Prod"; \
      echo "ServerSignature Off"; \
      echo "TraceEnable Off"; \
      echo "<DirectoryMatch \"^/var/www/html/(include|classes|uploads/prontuario)\">"; \
      echo "    Require all denied"; \
      echo "</DirectoryMatch>"; \
    } > /etc/apache2/conf-available/seguranca.conf && a2enconf seguranca

# PHP: limite de upload (fotos e exames), fuso horário, erros só no log e sessão protegida
RUN { \
      echo "upload_max_filesize=12M"; \
      echo "post_max_size=40M"; \
      echo "max_file_uploads=10"; \
      echo "date.timezone=America/Sao_Paulo"; \
      echo "expose_php=Off"; \
      echo "display_errors=Off"; \
      echo "log_errors=On"; \
      echo "session.cookie_httponly=1"; \
      echo "session.use_strict_mode=1"; \
      echo "session.cookie_samesite=Lax"; \
    } > /usr/local/etc/php/conf.d/clinica.ini
