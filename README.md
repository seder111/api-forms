# API Forms

Backend PHP reutilizable para recibir formularios HTML sin JavaScript. Utiliza
[Flight PHP](https://flightphp.com/) para las rutas y
[PHP dotenv](https://github.com/vlucas/phpdotenv) para la configuración.

El frontend puede estar creado con Astro, HTML estático u otra tecnología. Solo
necesita enviar un formulario mediante `POST`.

> Estado actual: valida nombre, email y consentimientos RGPD, y redirige al
> visitante. Todavía no guarda los datos ni envía correos.

## Requisitos

- PHP 8.2 o superior.
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
APP_ENV=production
APP_DEBUG=false
CONTACT_SUCCESS_URL=/gracias/
CONTACT_ERROR_URL=/contacto/?error=validation
```

- `CONTACT_SUCCESS_URL` es la página de destino tras un envío válido.
- `CONTACT_ERROR_URL` es la página de destino cuando falla la validación.
- Las dos rutas deben comenzar por `/` y pertenecer al mismo sitio.
- `APP_DEBUG` debe permanecer en `false` en producción.

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
# El campo "nom" del formulario se guarda como "name" en Plunk,
# y "telefon" se guarda tal cual.
PLUNK_CONTACT_FIELDS=nom:name,telefon
```

Si se deja vacía, solo se guardan el email y el estado de suscripción.

Los contactos que ya existen en Plunk no se modifican, con una excepción: si
el envío marca la casilla `newsletter` y el contacto no estaba suscrito, se le
suscribe. Nunca se des-suscribe a nadie desde el formulario ni se sobrescriben
sus datos.

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
  <label for="nom">Nombre</label>
  <input
    id="nom"
    name="nom"
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
      name="privacitat"
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

Los atributos `name` y los valores de las casillas deben coincidir exactamente
con los esperados por el backend:

| Campo | Tipo | Obligatorio | Valor esperado |
|---|---|---:|---|
| `nom` | Texto | Sí | Máximo 100 caracteres |
| `email` | Email | Sí | Email válido, máximo 254 caracteres |
| `privacitat` | Casilla | Sí | `1` |
| `newsletter` | Casilla | No | `1` cuando se acepta |

La validación HTML mejora la experiencia, pero la validación definitiva
siempre se realiza de nuevo en PHP.

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
  --data-urlencode "nom=Anna" \
  --data-urlencode "email=anna@example.com" \
  --data "privacitat=1" \
  --data "newsletter=1"
```

Debe responder con un estado `303` y esta cabecera:

```text
Location: /gracias/
```

### Envío inválido

```bash
curl -i -X POST https://ejemplo.com/api/contact \
  --data-urlencode "nom=Anna" \
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
6. Usa los mismos nombres de campos o adapta `ContactController.php`.
7. Crea las páginas de éxito y error configuradas en `.env`.
8. Comprueba `/api/` y realiza un envío de prueba.

Cada instalación debe tener su propio `.env`. No reutilices credenciales de una
web en otra.

## Adaptar los campos

Las rutas se encuentran en `src/routes.php` y la validación del formulario en
`src/Http/ContactController.php`.

Si otro formulario utiliza, por ejemplo, `name` en vez de `nom`, debes modificar
el atributo HTML y la lectura correspondiente en el controlador. Ambos lados
deben coincidir.

Después de crear nuevas clases o cambiar namespaces, regenera el autoload:

```bash
composer dump-autoload --optimize
```

## Despliegue de actualizaciones

Con el `.env` fuera de la carpeta privada, actualizar es sustituir la carpeta:

1. Haz una copia de seguridad si ya se almacenan datos (por ejemplo, registros
   en `storage/`, que también se pierde al sustituir la carpeta).
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

Comprueba que los nombres sean `nom`, `email` y `privacitat`, y que la casilla
obligatoria envíe `value="1"`.

## Seguridad

- Mantén `.env`, `vendor/`, `src/` y los registros fuera de `public_html`.
- Usa siempre HTTPS.
- Mantén `APP_DEBUG=false` en producción.
- No confíes únicamente en los atributos HTML como `required`.
- No muestres excepciones ni credenciales al visitante.
- No utilices permisos `777`.
- Actualiza las dependencias de forma controlada y revisa los cambios antes de
  desplegarlos.
- Conserva pruebas del consentimiento cuando se implemente la base de datos.
- Añade protección antispam y limitación de peticiones antes de exponer
  formularios con mucho tráfico.

## Siguientes mejoras previstas

- Guardar cada solicitud y sus consentimientos en una base de datos.
- Integrar el envío transaccional mediante Plunk.
- Registrar fecha y versión de la política de privacidad aceptada.
- Añadir honeypot y limitación de peticiones.
- Registrar errores internos sin exponer detalles al visitante.
