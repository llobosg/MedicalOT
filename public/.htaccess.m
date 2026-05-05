# Evitar listado de directorios
Options -Indexes

# Proteger archivos sensibles
<FilesMatch "^(config\.php|\.env|composer\.json|\.git)">
    Require all denied
</FilesMatch>

# Solo permitir acceso a archivos estáticos en assets
<FilesMatch "\.(css|js|png|jpg|jpeg|gif|svg|webp|ico|woff2?|ttf|eot)$">
    Require all granted
</FilesMatch>

# Forzar HTTPS en producción
<If "%{HTTP:X-Forwarded-Proto} != 'https' && %{ENV:RAILWAY_ENVIRONMENT} == 'true'">
    Redirect permanent / https://medicalot.com%{REQUEST_URI}
</If>

# Cache para assets estáticos
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>

# Punto de entrada único
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]