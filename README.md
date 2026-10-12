# Referrer-Policy para PrestaShop

Módulo gratuito que añade la cabecera HTTP `Referrer-Policy` a tu tienda PrestaShop y a su
panel de administración, con un valor distinto para cada zona.

**PrestaShop 1.7.8 a 9.2 · PHP 7.4 a 8.3 · sin overrides · sin Composer · licencia AFL-3.0**

[English summary below](#english-summary)

## Por qué

El panel de PrestaShop vive en una carpeta de nombre secreto. Pero el navegador le cuenta a
cada página de destino de dónde vienes, con la cabecera `Referer`. Si estás en el panel y
pulsas «Ver mi tienda» o la vista previa de un producto, la tienda recibe la dirección completa
del panel, con el nombre de la carpeta y el token de la sesión. De ahí pasa a los registros del
servidor y a la analítica y los píxeles de publicidad que tengas instalados.

PrestaShop no envía `Referrer-Policy` (comprobado hasta la 9.2.0). Este módulo la añade:

- en la **tienda**, `strict-origin-when-cross-origin`: dentro de tu sitio no cambia nada, y a
  los demás sitios solo se les dice el dominio, que es lo que necesitan la analítica y los
  afiliados;
- en el **panel**, `no-referrer`: desde el panel no se cuenta nunca de dónde vienes.

## Qué hace exactamente

- Emite `Referrer-Policy` en todas las páginas que genera PrestaShop: la tienda, el panel y su
  página de acceso. También en las páginas Symfony del panel de PrestaShop 9.
- Cada zona tiene su interruptor y su valor: `no-referrer`, `same-origin`, `strict-origin`,
  `strict-origin-when-cross-origin` u `origin`.
- Si ya hay una `Referrer-Policy` puesta desde PHP (otro módulo, o una futura opción nativa de
  PrestaShop), **no la sustituye ni la duplica**, y te lo dice en su pantalla.
- Nunca provoca un error: si la cabecera no se puede enviar, no hace nada.
- Repone un detalle del núcleo: PrestaShop usa el `Referer` para volver al listado después de
  activar o desactivar un registro en los listados antiguos del panel (transportistas, reglas
  de carrito, países…). Sin `Referer` te mandaría al escritorio; el módulo te devuelve al
  listado.
- No crea tablas, no modifica ficheros de PrestaShop y **no se conecta a ningún servidor**.

## Instalación

1. Descarga `ecom_referrerpolicy.zip` del
   [último release](https://github.com/ecomyseo/ecom_referrerpolicy/releases/latest).
2. Súbelo desde **Módulos > Gestor de módulos > Subir un módulo**.

No uses «Source code (zip)» ni **Code > Download ZIP**: en esos la carpeta lleva otro nombre y
PrestaShop no los acepta.

O clona el repositorio directamente dentro de `modules/`:

```
cd modules
git clone https://github.com/ecomyseo/ecom_referrerpolicy.git
```

Desde que se instala funciona con los valores recomendados.

## Configuración

En **Módulos > Gestor de módulos > Cabecera Referrer-Policy > Configurar** hay un formulario
con dos pestañas y un solo botón de guardar.

| Ajuste | De fábrica | Ámbito en multitienda |
|---|---|---|
| Emitir la cabecera en la tienda | Sí | Por tienda |
| Valor para la tienda | `strict-origin-when-cross-origin` | Por tienda |
| Emitir la cabecera en el panel | Sí | Global |
| Valor para el panel | `no-referrer` | Global |

Al guardar, el módulo vuelve a leer los valores de la base de datos y solo entonces confirma.

| Valor | Qué cuenta el navegador |
|---|---|
| `no-referrer` | Nada, nunca |
| `same-origin` | La dirección completa dentro de tu sitio; nada a otros sitios |
| `strict-origin` | Solo el dominio, y nada al pasar de HTTPS a HTTP |
| `strict-origin-when-cross-origin` | La dirección completa dentro de tu sitio; solo el dominio a otros sitios |
| `origin` | Solo el dominio, siempre |

Tienda y panel comparten dominio, así que `same-origin` en el panel no evita que la tienda
reciba la dirección del panel. Por eso el valor de fábrica es `no-referrer`.

## Comprobarlo

```
curl -s -o /dev/null -D - https://tutienda.com/ | grep -i referrer-policy
```

O con el navegador: F12, pestaña **Red**, recarga, pulsa la primera petición y busca
`Referrer-Policy` entre las cabeceras de respuesta.

## Cómo funciona

Un único hook, `actionDispatcherBefore`, que se ejecuta antes de que empiece la salida en la
tienda, en el panel antiguo y en las páginas Symfony del panel. El módulo comprueba
`headers_sent()` y `headers_list()`, coteja el valor con una lista cerrada y llama a
`header()`. Todo dentro de un `try/catch`.

Un segundo hook, `actionAdminStatusAfter`, repone la vuelta al listado descrita arriba.

## Límites

- **Cabeceras del servidor web.** Si Apache, nginx o una CDN ya añaden `Referrer-Policy`, PHP
  no puede verla y saldrían dos. Deja una sola: desactiva el módulo en esa zona o quítala del
  servidor.
- **Redirecciones internas del panel.** Las que Symfony resuelve antes de llegar a un
  controlador (una URL antigua que redirige a la nueva, o el salto al acceso cuando no hay
  sesión) salen sin la cabecera. No son páginas; la página en la que acaban sí la lleva.
- **Ficheros estáticos.** Imágenes, CSS y JavaScript los sirve el servidor web sin pasar por
  PrestaShop.
- **Módulos que lean el `Referer` en el panel.** Con `no-referrer` dejan de recibirlo. Si algo
  deja de funcionar, usa `same-origin` en el panel.

## Probado en

| PrestaShop | PHP |
|---|---|
| 1.7.8.11 | 7.4 |
| 8.2.7 | 8.1 |
| 9.1.1 | 8.2 |
| 9.2.0 | 8.2 |

En cada una: cabecera en respuestas reales de la tienda y del panel, guardado del formulario,
apagado, valores no admitidos y convivencia con una cabecera ya existente.

## Licencia y autor

[Academic Free License 3.0](LICENSE). Hecho por [Ecom Experts](mailto:ecomyseo@gmail.com).
Los fallos y las mejoras, en las *issues* de este repositorio.

---

## English summary

Free PrestaShop module that sends the `Referrer-Policy` HTTP header in the shop and in the
back office, with a separate value for each.

**PrestaShop 1.7.8 to 9.2 · PHP 7.4 to 8.3 · no overrides · no Composer · AFL-3.0**

- **Why:** the back office lives in a secret folder, but the browser tells the next page where
  you came from. Opening the shop from the back office hands the full back office URL, folder
  name and token included, to the shop, its server logs and its analytics scripts. PrestaShop
  does not send `Referrer-Policy` (checked up to 9.2.0).
- **Defaults:** `strict-origin-when-cross-origin` in the shop, `no-referrer` in the back
  office. Each area has its own switch and value (`no-referrer`, `same-origin`,
  `strict-origin`, `strict-origin-when-cross-origin`, `origin`).
- **How:** a single hook, `actionDispatcherBefore`, which runs before any output in the shop,
  in the legacy back office and in the Symfony pages of the PrestaShop 9 back office. If a
  `Referrer-Policy` header has already been set from PHP, the module neither replaces nor
  duplicates it. It never throws.
- **One core quirk handled:** PrestaShop uses the `Referer` to go back to the list after
  toggling a record's status in legacy back office lists. Without it you would land on the
  dashboard; the module sends you back to the list (`actionAdminStatusAfter`).
- **No tables, no core changes, no calls to any external server.**
- **Install:** download `ecom_referrerpolicy.zip` from the
  [latest release](https://github.com/ecomyseo/ecom_referrerpolicy/releases/latest) and upload
  it from the Module Manager, or `git clone` the repository inside `modules/`. Do not use
  "Source code (zip)": its folder name is not the one PrestaShop expects.
- **Limits:** a header added by the web server itself (Apache, nginx, a CDN) cannot be seen
  from PHP, so keep only one; redirects resolved by Symfony before reaching a controller and
  static files do not carry the header.

The interface is available in English and Spanish.
