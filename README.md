# 📚 BookGuest

![HTML5](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?logo=javascript&logoColor=black)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)
![Apache](https://img.shields.io/badge/Apache-XAMPP-D22128?logo=apache&logoColor=white)
![Firebase](https://img.shields.io/badge/Firebase-Storage-DD2C00?logo=firebase&logoColor=white)

**Tienda web de libros y panel administrativo**

Proyecto final del Grupo 2 para el curso **Ambiente Web Cliente/Servidor (SC-502)**.

BookGuest conecta la consulta del catálogo con el carrito, la compra y el seguimiento de pedidos. El equipo administrador gestiona libros, existencias, usuarios y promociones desde un panel propio.

## Contenido

- [Funciones](#funciones)
- [Tecnologías](#tecnologías)
- [Flujo de compra](#flujo-de-compra)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Instalación local](#instalación-local)
- [Primera cuenta de administración](#primera-cuenta-de-administración)
- [Imágenes y credenciales](#imágenes-y-credenciales)
- [Verificación rápida](#verificación-rápida)

## Funciones

| Área | Funciones |
| --- | --- |
| Cuenta | Registro, inicio y cierre de sesión, visibilidad de la contraseña y edición del perfil. |
| Tienda | Búsqueda por título, autor, ISBN, categoría o editorial, detalle de libro, ofertas y favoritos. |
| Compras | Carrito con edición de cantidades, confirmación de compra e historial de pedidos. |
| Administración | Dashboard, inventario, gestión de usuarios y roles, ofertas y cambios de estado de pedidos. |

El registro público asigna el rol `ROLE_CLIENTE`. El rol `ROLE_ADMIN` habilita el panel administrativo. La interfaz está disponible en español e inglés.

## Tecnologías

| Capa | Implementación |
| --- | --- |
| Navegador | HTML5, CSS3 y JavaScript. Los estilos, el carrusel, los avisos y los menús son propios. |
| Acceso | El formulario HTML usa Fetch para consultar la sesión y enviar las credenciales a PHP. |
| Servidor | Apache de XAMPP y PHP 8.1 o superior. |
| Base de datos | MySQL 8.0, con PDO y consultas preparadas. |
| Imágenes | Firebase Storage almacena portadas y fotos de perfil. MySQL conserva las URL de descarga. |
| Iconos | Font Awesome Free 7.2.0 mediante jsDelivr. |
| Herramientas | Visual Studio Code, MySQL Workbench, Git y GitHub. |

El navegador descarga los iconos desde [jsDelivr](https://www.jsdelivr.com/package/npm/@fortawesome/fontawesome-free). La versión está fijada en el código y la hoja de estilos se verifica mediante su atributo `integrity`.

## Flujo de compra

1. El usuario crea su cuenta e inicia sesión. PHP verifica sus credenciales y lo dirige al panel de su rol.
2. El cliente consulta libros, guarda favoritos y añade productos al carrito.
3. PHP calcula la cotización con los precios y descuentos vigentes. Si cambian antes de confirmar, solicita revisar el nuevo total.
4. Una transacción en MySQL guarda el pedido y el precio de cada línea, descuenta existencias y cierra el carrito.
5. El cliente consulta sus pedidos. El administrador puede cambiar su estado. Al cancelar un pedido se devuelven las unidades al inventario.

> [!NOTE]
> La compra es una simulación académica. La interfaz registra tarjeta o efectivo y muestra importes en colones costarricenses (₡).

## Estructura del proyecto

```text
BookGuest/
├── app/
│   ├── bootstrap.php         # Sesiones, permisos y utilidades
│   ├── catalogo.php          # Libros, favoritos, precios y ofertas
│   ├── conexion.php          # Conexión PDO y transacciones
│   ├── cuentas.php           # Autenticación y cuentas
│   ├── firebase.php          # Integración con Storage
│   └── pedidos.php           # Carrito, pedidos y existencias
├── database/
│   └── esquema.sql           # Tablas, relaciones y roles iniciales
├── public/                   # Directorio publicado por Apache
│   ├── api/                  # Endpoints de login, cuenta, cliente y admin
│   ├── css/                  # Estilos base y de cada vista
│   ├── js/                   # Interacciones de acceso, tienda y panel
│   ├── index.php             # Enrutamiento de vistas PHP
│   └── login.html            # Pantalla de inicio de sesión
├── views/                    # Plantillas y componentes PHP
├── .gitignore
├── config.example.php        # Plantilla de configuración
└── README.md
```

El acceso pasa por [login.html](public/login.html), [login.js](public/js/login.js) y [api/login.php](public/api/login.php). El servidor entrega el idioma y el token CSRF al preparar el formulario. Tras validar las credenciales, devuelve la ruta de la tienda o del dashboard.

## Instalación local

### 1. Obtener el proyecto

Instala XAMPP con Apache y PHP 8.1 o superior, MySQL 8.0 y Git. MySQL Workbench facilita la importación del esquema. En PHP habilita `PDO`, `pdo_mysql`, `mbstring`, `fileinfo` y `openssl`.

```powershell
git clone https://github.com/LilCisco/BookGuest-Tienda-de-libros-Online---G2.git BookGuest
Set-Location BookGuest
```

### 2. Crear una base e importar el esquema

Desde una conexión administrativa en MySQL, crea la base y un usuario para la aplicación. Cambia la contraseña de ejemplo por una propia.

```sql
CREATE DATABASE bookguest_sc502
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'bookguest_sc502'@'127.0.0.1'
  IDENTIFIED BY 'REEMPLAZAR_POR_UNA_CLAVE_PROPIA';

GRANT SELECT, INSERT, UPDATE, DELETE
  ON bookguest_sc502.*
  TO 'bookguest_sc502'@'127.0.0.1';

USE bookguest_sc502;
```

Importa [database/esquema.sql](database/esquema.sql) con la conexión administrativa sobre esa base vacía. El esquema crea 13 tablas y los dos roles iniciales. Las cuentas y los libros se registran desde la aplicación.

### 3. Crear la configuración privada

```powershell
Copy-Item .\config.example.php .\config.local.php
```

Completa `config.local.php` con el host, puerto, base, usuario y contraseña de MySQL. Usa el puerto de tu servidor. La [plantilla de configuración](config.example.php) también incluye el bloque `firebase` para configurar Storage.

### 4. Configurar Apache

Agrega este ejemplo a `httpd.conf` de XAMPP y sustituye las dos rutas por la ruta absoluta de `public/` en tu equipo.

```apache
Alias /bookguest "C:/ruta/BookGuest/public"

<Directory "C:/ruta/BookGuest/public">
    Options -Indexes
    AllowOverride None
    Require local
    DirectoryIndex index.php
</Directory>
```

Publica únicamente `public/`. Inicia MySQL, reinicia Apache y abre [BookGuest en localhost](http://localhost/bookguest/). Si Apache escucha en otro puerto, inclúyelo en la URL, por ejemplo `http://localhost:8095/bookguest/`.

## Primera cuenta de administración

Registra una cuenta desde el formulario y cierra sesión. En MySQL Workbench, selecciona la base de BookGuest y ejecuta la siguiente consulta con el correo de esa cuenta.

```sql
UPDATE usuario_rol AS ur
JOIN usuario AS u ON u.id_usuario = ur.id_usuario
JOIN rol AS r ON r.nombre = 'ROLE_ADMIN'
SET ur.id_rol = r.id_rol
WHERE u.email = 'CORREO_DE_LA_CUENTA_REGISTRADA';
```

Inicia sesión de nuevo para abrir el dashboard y registrar los primeros libros.

## Imágenes y credenciales

Para cargar portadas y fotos, configura el nombre exacto en `firebase.bucket` y la ruta absoluta del JSON de la cuenta de servicio en `firebase.credentials`. Las cargas nuevas se organizan en `libros/` y `perfiles/` dentro de la ruta indicada por `firebase.prefix`. Si reutilizas imágenes existentes, conserva el bucket de sus URL.

En `php.ini`, habilita `allow_url_fopen` y `file_uploads`. Ajusta `upload_max_filesize` a `2M`, `post_max_size` a `4M` y `openssl.cafile` a la ruta del certificado CA de tu instalación. BookGuest acepta JPG, PNG y WebP de hasta 2 MB y 5000 × 5000 píxeles. El alta de un libro requiere portada.

> [!IMPORTANT]
> Mantén `config.local.php` y el JSON de la cuenta de servicio fuera del repositorio y del directorio público. Comparte `config.example.php` para que cada integrante use su propia configuración.

La carga inicial de los iconos y el acceso a Firebase requieren conexión a Internet.

## Verificación rápida

- **Acceso:** registra una cuenta de cliente, prueba el ojo de la contraseña e inicia sesión.
- **Tienda:** busca un libro, guárdalo como favorito, añádelo al carrito y confirma una compra de prueba.
- **Administración:** inicia sesión como administrador, crea una oferta, cambia el estado del pedido y verifica las existencias al cancelarlo.
- **Imágenes:** carga una portada desde inventario o una foto desde el perfil y comprueba que la vista la muestre tras recargar.
