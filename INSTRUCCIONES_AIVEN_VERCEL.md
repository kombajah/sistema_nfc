# Conectar el sistema a Aiven y publicarlo en Vercel

## 1. Certificado CA
El archivo `certs/ca.pem` ya está incluido en este paquete (es el certificado de tu
proyecto de Aiven). Súbelo tal cual a tu repositorio de Git, en esa misma ruta
`certs/ca.pem`. Es un certificado público, no una credencial: no hay problema en
que quede en el repositorio.

## 2. Variables de entorno en Vercel (credenciales reales)
Ve a tu proyecto en vercel.com → **Settings → Environment Variables** y agrega:

| Nombre    | Valor                                                   |
|-----------|----------------------------------------------------------|
| DB_HOST   | mysql-sistema-nfc-sistema-nfc.d.aivencloud.com            |
| DB_PORT   | 18347                                                     |
| DB_NAME   | defaultdb                                                 |
| DB_USER   | avnadmin                                                  |
| DB_PASS   | (tu contraseña real de Aiven, cópiala del panel)          |

Guárdalas y vuelve a desplegar (Vercel no aplica variables nuevas a un deploy ya hecho).
**No pongas la contraseña real dentro de `conexion.php` ni la subas a git.**

## 3. Crear las tablas en Aiven
En el panel de Aiven, en la pestaña de consultas (o con un cliente MySQL como
DBeaver/TablePlus usando estos mismos datos + el certificado), ejecuta `schema.sql`
completo. Crea todas las tablas, incluida `sesiones`.

## 4. Crear el administrador inicial
Abre en el navegador: `https://TU-PROYECTO.vercel.app/instalar.php`
Crea el usuario `admin` / `1234`. Entra, cambia esa clave desde "Maestros" y
luego borra `instalar.php` del repositorio (o coméntale su contenido) para que
nadie más pueda volver a ejecutarlo.

## 5. Probar en local (opcional)
Copia `config.local.example.php` a `config.local.php`, pon ahí tu contraseña real
de Aiven, y corre el proyecto con XAMPP. `config.local.php` está en `.gitignore`
para que nunca se suba por accidente.

## Notas técnicas de este cambio
- **Conexión SSL:** `conexion.php` ahora se conecta con `MYSQLI_CLIENT_SSL` y el
  certificado `certs/ca.pem`, como exige Aiven (`ssl-mode=REQUIRED`).
- **Sesiones en base de datos:** Vercel ejecuta PHP como funciones serverless; cada
  solicitud puede atenderla un contenedor distinto, así que las sesiones basadas en
  archivos (las de PHP por defecto) se perderían y el login fallaría de forma
  intermitente. Por eso las sesiones ahora se guardan en la tabla `sesiones` de tu
  propia base de datos Aiven, a través de `iniciar_sesion()` en `conexion.php`.
- Recuerda rotar la contraseña de Aiven si alguna vez quedó visible en una captura,
  un chat o un commit.
