# API Forms

Backend PHP reutilizable para recibir formularios HTML sin JavaScript. Utiliza
[Flight PHP](https://flightphp.com/) para las rutas y
[PHP dotenv](https://github.com/vlucas/phpdotenv) para la configuración.

El frontend puede estar creado con Astro, HTML estático u otra tecnología. Solo
necesita enviar un formulario mediante `POST`.

> Estado actual: valida los campos configurados en el `.env`, envía un email
> de notificación mediante Plunk (activado por defecto, desactivable),
> opcionalmente guarda al remitente como contacto en Plunk, opcionalmente
> guarda cada envío en una base de datos propia (MySQL, MariaDB o SQLite) y
> redirige al visitante.

## Requisitos

- PHP 8.2 o superior, con la extensión PDO (y su driver `pdo_mysql` o
  `pdo_sqlite` solo si se activa el guardado en base de datos).
- Apache con `mod_rewrite` y soporte para `.htaccess`.
- Composer en local o en el servidor.
- HTTPS en producción.

## Estructura recomendada en el servidor

La aplicación debe estar fuera del directorio público. Solo el punto de entrada
de la API se publica.

```text
/home/usuario/
├── .env
│
├── api-forms/
│   ├── src/
│   ├── storage/
│   ├── vendor/
│   ├── bootstrap.php
│   ├── composer.json
│   └── composer.lock
│
└── public_html/
    ├── index.html
    ├── assets/
    └── api/
        ├── index.php
        └── .htaccess
```

El `.env` vive junto a `api-forms/`, no dentro: así, actualizar la aplicación
consiste en sustituir la carpeta `api-forms/` completa sin tocar la
configuración. Por compatibilidad, un `.env` dentro de `api-forms/` también
funciona y, si existen los dos, tiene prioridad el de dentro.

De esta manera, `.env`, el código PHP, las dependencias y los futuros registros
no se pueden descargar desde el navegador.

## Instalación en un proyecto nuevo

### 1. Copiar la aplicación privada

Copia este repositorio al servidor con el nombre `api-forms`, a la misma altura
que `public_html`.

No copies el `.env` de otro proyecto porque contendrá su configuración y, en el
futuro, sus credenciales.

### 2. Instalar las dependencias

Si el servidor dispone de Composer:

```bash
cd /home/usuario/api-forms
composer install --no-dev --optimize-autoloader --no-interaction
```

Se debe utilizar `composer install`, no `composer update`, para respetar las
versiones de `composer.lock`.

Si el hosting no dispone de Composer, ejecuta el comando anterior en tu
ordenador y sube también la carpeta `vendor/`. La versión local de PHP debe ser
compatible con la del servidor.

### 3. Crear la configuración

Copia `.env.example` como `.env` al lado de la carpeta privada (en
`/home/usuario/.env`, a la misma altura que `api-forms/`):

```dotenv
APP_DEBUG=false
CONTACT_SUCCESS_URL=/gracias/
CONTACT_ERROR_URL=/contacto/?error=validation
```

- `CONTACT_SUCCESS_URL` es la página de destino tras un envío válido.
- `CONTACT_ERROR_URL` es la página de destino cuando falla la validación.
- Las dos rutas deben comenzar por `/` y pertenecer al mismo sitio.
- `APP_DEBUG` debe permanecer en `false` en producción.

Añade también las credenciales de [Plunk](https://useplunk.com/), que se usan
para el email de notificación y para guardar contactos:

```dotenv
PLUNK_API_KEY=
PLUNK_FROM=
PLUNK_NAME_FROM=
CONTACT_RECIPIENT=
```

- `PLUNK_API_KEY` es la clave secreta de la API de Plunk.
- `PLUNK_FROM` es la dirección remitente del email de notificación (debe estar
  verificada en Plunk).
- `PLUNK_NAME_FROM` es el nombre que se muestra como remitente.
- `CONTACT_RECIPIENT` es la dirección que recibe las notificaciones.

Si falta esta configuración, la API no falla: registra el error en el log del
servidor y redirige al visitante igualmente.

### Contactos en Plunk

Guardar los remitentes como contactos en Plunk es opcional y está desactivado
por defecto: cada web debe activarlo explícitamente con
`PLUNK_SAVE_CONTACTS=true` en su `.env`. Sin esa variable, la API solo envía
los emails de notificación.

Con la función activada, cada envío válido se guarda como contacto en Plunk.
El email identifica el registro y la casilla `newsletter` determina la
suscripción: si el formulario no envía ese campo, el contacto queda como no
suscrito.

`PLUNK_CONTACT_FIELDS` define qué otros campos del formulario se guardan en
los datos del contacto, separados por comas. Cada entrada puede ser `campo` o
`campo:claveEnPlunk` para guardarlo con otro nombre:

```dotenv
# El campo "name" del formulario se guarda tal cual,
# y "phone" se guarda como "telefono" en Plunk.
PLUNK_CONTACT_FIELDS=name,phone:telefono
```

Si se deja vacía, solo se guardan el email y el estado de suscripción.

Los contactos que ya existen en Plunk no se modifican, con una excepción: si
el envío marca la casilla `newsletter` y el contacto no estaba suscrito, se le
suscribe. Nunca se des-suscribe a nadie desde el formulario ni se sobrescriben
sus datos.

En sentido inverso, el email de notificación también puede desactivarse con
`PLUNK_SEND_EMAIL=false` para despliegues que solo guardan contactos en
Plunk. Está activado por defecto: sin esa variable, cada envío válido genera
el email. Con el email desactivado, `PLUNK_FROM`, `PLUNK_NAME_FROM` y
`CONTACT_RECIPIENT` dejan de ser necesarias.

### Guardar los envíos en una base de datos

Guardar cada envío en una base de datos propia es opcional y está desactivado
por defecto: cada web debe activarlo explícitamente con
`DB_SAVE_SUBMISSIONS=true` en su `.env`. Sin esa variable no se abre ninguna
conexión y todo funciona como hasta ahora.

Con la función activada, cada envío válido se guarda en la tabla
`submissions` (email, todos los campos como JSON y fecha). La tabla se crea
automáticamente en el primer uso: no hay que ejecutar ningún script SQL.

```dotenv
DB_SAVE_SUBMISSIONS=true

# "mysql" (también MariaDB; es el valor por defecto) o "sqlite".
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=miweb
DB_USER=miweb
DB_PASSWORD=secreto
```

Para SQLite no hacen falta credenciales: con `DB_DRIVER=sqlite` la base de
datos es un fichero, por defecto `storage/database.sqlite` dentro de la
carpeta privada (fuera de `public_html`, como el resto de la aplicación).
`DB_SQLITE_PATH` permite cambiar la ruta; si es relativa, se resuelve contra
la carpeta de la aplicación. Nunca coloques ese fichero dentro del webroot
público.

Como el resto de integraciones, el guardado es *best-effort*: si la base de
datos no está disponible, el error se registra en el log del servidor y el
visitante es redirigido a la página de éxito igualmente.

El archivo `.env` no debe subirse a Git ni colocarse dentro de `public_html`.

### 4. Publicar el punto de entrada

Copia la carpeta `doc_public/api/` de este repositorio a `public_html/api/`.

```text
doc_public/api/index.php    → public_html/api/index.php
doc_public/api/.htaccess    → public_html/api/.htaccess
```

No es necesario copiar `doc_public/index.html`: es solo la web utilizada como
ejemplo durante el desarrollo.

El archivo público busca la aplicación en:

```text
/home/usuario/api-forms/bootstrap.php
```

Si cambias el nombre de la carpeta privada, también debes cambiar la ruta
`/api-forms/bootstrap.php` en `doc_public/api/index.php`.

## Integrar un formulario

El formulario no necesita JavaScript. Debe apuntar a `/api/contact` y utilizar
el método `POST`:

```html
<form action="/api/contact" method="POST">
  <label for="name">Nombre</label>
  <input
    id="name"
    name="name"
    type="text"
    maxlength="100"
    required
  >

  <label for="email">Email</label>
  <input
    id="email"
    name="email"
    type="email"
    maxlength="254"
    required
  >

  <label>
    <input
      name="privacy"
      type="checkbox"
      value="1"
      required
    >
    He leído y acepto la política de privacidad.
  </label>

  <label>
    <input
      name="newsletter"
      type="checkbox"
      value="1"
    >
    Quiero recibir comunicaciones periódicas.
  </label>

  <button type="submit">Enviar</button>
</form>
```

El backend acepta cualquier conjunto de campos: todos los que envíe el
formulario se incluyen en el email de notificación. Los papeles especiales se
asignan en el `.env`, no en el código:

| Variable | Qué hace | Por defecto |
|---|---|---|
| `CONTACT_EMAIL_FIELD` | Campo que contiene el email (siempre obligatorio, se valida como dirección) | `email` |
| `CONTACT_NEWSLETTER_FIELD` | Casilla que suscribe a la newsletter (`value="1"`); ausente = no suscrito | `newsletter` |
| `CONTACT_REQUIRED_FIELDS` | Campos obligatorios además del email, separados por comas | vacío |
| `CONTACT_CHECKBOX_FIELDS` | Casillas que se muestran como Sí/No en el email de notificación | vacío |

Los `name` del formulario deben coincidir con lo declarado en el `.env`. Las
casillas deben enviar `value="1"`. La validación HTML mejora la experiencia,
pero la validación definitiva siempre se realiza de nuevo en PHP: el email
debe ser válido (máximo 254 caracteres), los campos de
`CONTACT_REQUIRED_FIELDS` deben llegar con valor y ningún campo puede superar
los 5000 caracteres.

## Páginas que debe tener la web

Con la configuración predeterminada, el frontend debe incluir:

```text
/gracias/     Envío aceptado
/contacto/    Formulario o página donde mostrar el error
```

Cuando la validación falla, la API redirige a:

```text
/contacto/?error=validation
```

Astro puede consultar ese parámetro para mostrar un aviso, aunque no es
obligatorio.

## Comprobar la instalación

### Comprobación de estado

Visita:

```text
https://ejemplo.com/api/
```

La respuesta correcta es:

```json
{"status":"ok","service":"api-forms"}
```

### Envío válido

```bash
curl -i -X POST https://ejemplo.com/api/contact \
  --data-urlencode "name=Anna" \
  --data-urlencode "email=anna@example.com" \
  --data "privacy=1" \
  --data "newsletter=1"
```

Debe responder con un estado `303` y esta cabecera:

```text
Location: /gracias/
```

### Envío inválido

```bash
curl -i -X POST https://ejemplo.com/api/contact \
  --data-urlencode "name=Anna" \
  --data-urlencode "email=email-incorrecto"
```

Debe responder con:

```text
HTTP 303
Location: /contacto/?error=validation
```

## Reutilizarlo en varias webs

Para usarlo en otro proyecto:

1. Copia `api-forms` fuera de su `public_html`.
2. Instala o sube `vendor/`.
3. Crea un `.env` específico para esa web.
4. Copia `doc_public/api/` a su `public_html/api/`.
5. Añade `action="/api/contact" method="POST"` a su formulario.
6. Usa los mismos nombres de campos o adapta las variables `CONTACT_*` del
   `.env` (no hace falta tocar PHP).
7. Crea las páginas de éxito y error configuradas en `.env`.
8. Comprueba `/api/` y realiza un envío de prueba.

Cada instalación debe tener su propio `.env`. No reutilices credenciales de una
web en otra.

## Adaptar los campos

No hace falta tocar PHP: los campos se adaptan por `.env`. Si otro formulario
utiliza, por ejemplo, `nom` en vez de `name` y `subscribe` en vez de
`newsletter`:

```dotenv
CONTACT_EMAIL_FIELD=email
CONTACT_NEWSLETTER_FIELD=subscribe
CONTACT_REQUIRED_FIELDS=nom,privacy
CONTACT_CHECKBOX_FIELDS=privacy,subscribe
PLUNK_CONTACT_FIELDS=nom:name
```

Las rutas se encuentran en `src/routes.php`. Si algún día creas nuevas clases
o cambias namespaces, regenera el autoload:

```bash
composer dump-autoload --optimize
```

## Despliegue de actualizaciones

Con el `.env` fuera de la carpeta privada, actualizar es sustituir la carpeta:

1. Haz una copia de seguridad si ya se almacenan datos (por ejemplo, la base
   de datos SQLite o los registros en `storage/`, que se pierde al sustituir
   la carpeta).
2. Reemplaza la carpeta `api-forms/` completa por la nueva versión.
3. Ejecuta `composer install --no-dev --optimize-autoloader` o incluye
   `vendor/` en la carpeta que subes.
4. Copia `doc_public/api/` solamente si ha cambiado el punto de entrada.
5. Repite las comprobaciones anteriores.

El `.env` del servidor no se toca porque vive fuera de `api-forms/`. Si tu
instalación es anterior y aún lo tiene dentro, muévelo un nivel arriba antes
de actualizar de esta forma.

## Resolución de problemas

### `/api/` devuelve 500

Comprueba:

- Que `api-forms` y `public_html` estén a la misma altura.
- Que exista `api-forms/vendor/autoload.php`.
- Que el servidor utilice PHP 8.2 o superior.
- Que la ruta de `public_html/api/index.php` coincida con el nombre de la
  carpeta privada.
- El registro `php-fpm.log` del hosting.

### `/api/` funciona pero `/api/contact` devuelve 404

Apache no está aplicando la reescritura. Comprueba que:

- `public_html/api/.htaccess` se haya subido; algunos clientes FTP ocultan los
  archivos que comienzan por punto.
- El hosting permita `mod_rewrite` y `AllowOverride`.
- La API esté alojada bajo Apache. Nginx necesita una regla equivalente en su
  configuración y no utiliza `.htaccess`.

### El formulario devuelve 405

La ruta solo acepta `POST`. Comprueba que el formulario tenga:

```html
method="POST"
```

### Siempre redirige al error

Comprueba que el campo declarado en `CONTACT_EMAIL_FIELD` llegue con un email
válido, que los campos de `CONTACT_REQUIRED_FIELDS` existan en el formulario
con esos mismos nombres, y que las casillas obligatorias envíen `value="1"`.

## Seguridad

- Mantén `.env`, `vendor/`, `src/` y los registros fuera de `public_html`.
- Usa siempre HTTPS.
- Mantén `APP_DEBUG=false` en producción.
- No confíes únicamente en los atributos HTML como `required`.
- No muestres excepciones ni credenciales al visitante.
- No utilices permisos `777`.
- Actualiza las dependencias de forma controlada y revisa los cambios antes de
  desplegarlos.
- Con el guardado en base de datos activado, los envíos (incluidas las
  casillas de consentimiento) quedan registrados con fecha; protege esa base
  de datos como cualquier otro dato personal.
- Añade protección antispam y limitación de peticiones antes de exponer
  formularios con mucho tráfico.

## Siguientes mejoras previstas

- Registrar la versión de la política de privacidad aceptada.
- Añadir honeypot y limitación de peticiones.
- Registrar errores internos en fichero (`storage/logs/`) sin exponer
  detalles al visitante.
