# Sistema de Inventario

Aplicación web para la gestión de inventario desarrollada sobre **CodeIgniter 4**, con vistas **Twig**, autenticación propia, administración de usuarios/roles/permisos, control de stock, movimientos de inventario y exportación de reportes a Excel.

El proyecto está organizado por módulos y separa la lógica de aplicación, dominio e infraestructura para facilitar el mantenimiento y la incorporación de nuevas funcionalidades.

---

## Características principales

- Inicio de sesión con control de sesiones activas.
- Cambio y restablecimiento de contraseña.
- Política de contraseñas configurable.
- Limitación de intentos de inicio de sesión y acciones sensibles.
- Administración de usuarios.
- Administración de roles.
- Administración de módulos y permisos.
- Catálogo de productos.
- Catálogo de tipos de producto.
- Catálogo de tipos de movimiento.
- Registro de ingresos y salidas de inventario.
- Validación de stock antes de registrar una salida.
- Consulta del inventario actual.
- Kardex o historial por producto.
- Dashboard con indicadores de inventario.
- Búsqueda de productos desde el formulario de movimientos.
- Búsqueda y paginación en los principales listados.
- Reportes filtrables por fecha, operación y texto.
- Exportación de reportes en formato `.xlsx`.
- Histórico de reportes generados.
- Interfaz responsive con sidebar adaptable y tablas preparadas para dispositivos móviles.
- Protección CSRF y filtros de autorización por módulo y acción.

---

## Tecnologías

- PHP 8.2 o superior.
- CodeIgniter 4.
- Twig.
- MySQL / MariaDB mediante el driver `MySQLi`.
- PhpSpreadsheet para exportación de archivos Excel.
- HTML5, CSS y JavaScript vanilla.
- Composer para gestión de dependencias.

> El archivo `public/index.php` del proyecto exige PHP 8.2 como versión mínima.

---

## Estructura principal

```text
inventario/
├── app/
│   ├── Config/
│   │   ├── Routes.php
│   │   ├── RouteModules.php
│   │   ├── Services.php
│   │   └── SystemSettings.php
│   ├── Database/
│   │   ├── Migrations/
│   │   └── Seeds/
│   ├── Helpers/
│   ├── Libraries/
│   │   └── TwigView.php
│   ├── Src/
│   │   ├── Modules/
│   │   │   ├── Administration/
│   │   │   ├── Authentication/
│   │   │   └── Inventory/
│   │   └── Shared/
│   └── Views/
├── public/
│   ├── assets/
│   │   ├── css/
│   │   └── js/
│   └── index.php
├── writable/
│   ├── cache/
│   ├── logs/
│   └── reports/
├── vendor/
├── .env
├── composer.json
└── spark
```

### Arquitectura modular

Los módulos principales se encuentran en:

```text
app/Src/Modules/
```

Cada módulo puede dividirse en:

```text
Application/
Domain/
Infrastructure/
```

Actualmente existen tres módulos principales:

- `Authentication`: login, sesiones, cambio de contraseña y autorización.
- `Administration`: usuarios, roles, módulos y permisos.
- `Inventory`: productos, movimientos, inventario, configuraciones y reportes.

Las rutas no se concentran en un único archivo. Cada módulo registra sus propias rutas y `app/Config/RouteModules.php` funciona como registro central de proveedores de rutas.

---

## Requisitos

Antes de instalar el sistema, verificar:

- PHP 8.2 o superior.
- Composer.
- MySQL o MariaDB.
- Servidor web Apache o Nginx.
- Extensiones PHP requeridas por CodeIgniter y las dependencias instaladas.
- Extensión `mysqli` habilitada.
- Extensión `mbstring` habilitada.
- Extensión `intl` recomendada para CodeIgniter.
- Permisos de escritura sobre `writable/`.

Las dependencias externas utilizadas directamente por el código incluyen:

```text
twig/twig
phpoffice/phpspreadsheet
```

---

## Instalación

### 1. Obtener el proyecto

Ubica el proyecto en el directorio de trabajo del servidor:

```bash
cd /ruta/del/proyecto
```

### 2. Instalar dependencias

Si el proyecto completo contiene `composer.json`:

```bash
composer install
```

Para producción se recomienda:

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Crear el archivo `.env`

Si existe el archivo `env` de CodeIgniter:

```bash
cp env .env
```

En Windows también puedes copiarlo manualmente y renombrarlo a `.env`.

### 4. Configurar el entorno

Ejemplo mínimo:

```ini
CI_ENVIRONMENT = development

app.baseURL = 'http://localhost:8080/'
app.appTimezone = 'America/Lima'

# Base de datos
database.default.hostname = localhost
database.default.database = prueba
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.DBPrefix =
database.default.port = 3306

# Usuario administrador inicial
auth.adminUsername = admin
auth.adminEmail = admin@inventario.local
auth.adminPassword = CambiarEstaClave123!
```

Para producción:

```ini
CI_ENVIRONMENT = production
```

> En producción `auth.adminPassword` debe estar definido antes de ejecutar `AuthSeeder`. El seeder evita crear el administrador con una contraseña por defecto cuando el entorno es `production`.

### 5. Crear la base de datos

Ejemplo:

```sql
CREATE DATABASE prueba
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

### 6. Ejecutar migraciones

```bash
php spark migrate
```

Esto crea las tablas necesarias para autenticación, permisos e inventario.

### 7. Ejecutar seeders

Crear roles, permisos, módulos y usuario administrador:

```bash
php spark db:seed AuthSeeder
```

Crear catálogos iniciales de inventario:

```bash
php spark db:seed InventorySeeder
```

El `InventorySeeder` incorpora inicialmente ejemplos como:

- Tipos de producto: Caja, Bolsa, Blíster, Paquete y Unidad suelta.
- Tipos de movimiento: Movimiento interno, Ajuste por merma, Compra a proveedor y Venta a cliente.

### 8. Verificar permisos de `writable`

En Linux:

```bash
chmod -R 775 writable
```

El usuario que ejecuta PHP/Apache/Nginx debe poder escribir en este directorio.

Los reportes Excel se generan en:

```text
writable/reports/
```

En producción Twig también puede utilizar:

```text
writable/cache/twig/
```

### 9. Ejecutar en desarrollo

```bash
php spark serve
```

Por defecto estará disponible en:

```text
http://localhost:8080
```

---

## Usuario administrador inicial

El seeder obtiene las credenciales desde `.env`:

```ini
auth.adminUsername
auth.adminEmail
auth.adminPassword
```

En un entorno diferente de producción, si no se configuran valores personalizados, el código contempla los siguientes valores de desarrollo:

```text
Usuario: admin
Correo: admin@inventario.local
Contraseña: Admin123!2026
```

El usuario se crea con la opción de cambio obligatorio de contraseña activada.

> No utilices la contraseña de desarrollo en producción.

---

## Módulos del sistema

### Dashboard

Ruta:

```text
/dashboard
```

Muestra un resumen del inventario, entre otros datos:

- Productos activos.
- Stock total.
- Productos sin stock.
- Movimientos del día.
- Entradas del día.
- Salidas del día.
- Movimientos recientes.
- Productos con mayor stock.

Los límites de los listados del dashboard se configuran en `app/Config/SystemSettings.php`.

### Productos

Ruta principal:

```text
/products
```

Permite:

- Listar productos.
- Buscar productos.
- Crear productos.
- Editar productos.
- Eliminar productos mediante soft delete.
- Definir tipo de producto.
- Definir unidades por empaque.
- Definir precio.
- Activar o desactivar productos.

### Movimientos

Ruta principal:

```text
/movements
```

Permite registrar ingresos y salidas de inventario.

El proceso valida:

- Producto existente y activo.
- Tipo de movimiento activo.
- Compatibilidad entre el tipo de movimiento y la operación `IN` / `OUT`.
- Cantidad mayor a cero.
- Stock disponible para una salida.

El registro del movimiento y la actualización del stock se ejecutan dentro de una transacción.

El buscador remoto de productos está disponible en:

```text
/movements/products/search
```

### Inventario

Ruta principal:

```text
/inventory
```

Permite consultar el stock actual de los productos.

El historial o kardex de un producto está disponible en:

```text
/inventory/{id}/history
```

### Reportes

Ruta principal:

```text
/reports
```

Permite filtrar movimientos por:

- Fecha desde.
- Fecha hasta.
- Operación de ingreso o salida.
- Texto de búsqueda.

Los reportes pueden exportarse a Excel mediante PhpSpreadsheet.

Histórico:

```text
/reports/history
```

Descarga:

```text
/reports/{id}/download
```

Cada exportación queda registrada en la base de datos junto con el usuario, parámetros, cantidad de filas y fecha de generación.

### Tipos de producto

Ruta:

```text
/settings/product-types
```

Permite administrar el catálogo utilizado para clasificar productos.

### Tipos de movimiento

Ruta:

```text
/settings/movement-types
```

Permite configurar si un tipo de movimiento acepta:

```text
IN
OUT
BOTH
```

### Administración de usuarios

Ruta:

```text
/admin/users
```

Permite:

- Crear usuarios.
- Editar usuarios.
- Asignar roles.
- Restablecer contraseñas.
- Revocar sesiones activas.
- Eliminar usuarios según permisos.

### Roles

Ruta:

```text
/admin/roles
```

Permite administrar roles y su matriz de permisos.

### Módulos y permisos

Ruta:

```text
/admin/modules
```

Cada módulo puede habilitar las acciones:

```text
view
create
edit
delete
```

Las rutas utilizan el filtro `permission` para comprobar el permiso antes de ejecutar el controlador.

Ejemplo:

```php
['filter' => 'permission:products,edit']
```

---

## Tablas principales

| Tabla | Descripción |
|---|---|
| `users` | Usuarios registrados en el sistema. |
| `roles` | Roles disponibles. |
| `user_roles` | Relación entre usuarios y roles. |
| `modules` | Módulos registrados para el sistema de autorización. |
| `role_module_permissions` | Permisos por rol y módulo. |
| `user_sessions` | Sesiones activas de los usuarios. |
| `auth_audit_logs` | Auditoría de operaciones relacionadas con seguridad y administración. |
| `inventory_product_types` | Tipos de producto. |
| `inventory_movement_types` | Tipos de movimiento. |
| `products` | Catálogo de productos y stock actual. |
| `inventory_movements` | Kardex de entradas y salidas. |
| `inventory_report_exports` | Histórico de archivos Excel generados. |

---

## Configuración centralizada

Las opciones funcionales principales se encuentran en:

```text
app/Config/SystemSettings.php
```

Entre ellas:

### Sesiones

```php
public int $inactivityTimeout = 3600;
public int $maxActiveSessions = 3;
```

### Intentos de login

```php
public int $loginUserAttempts = 5;
public int $loginIpAttempts = 10;
public int $loginAttemptWindow = 300;
```

### Contraseñas

```php
public int $passwordMinLength = 6;
public int $passwordMaxLength = 128;
public bool $passwordRequireUppercase = false;
public bool $passwordRequireLowercase = false;
public bool $passwordRequireNumber = false;
public bool $passwordRequireSpecial = false;
```

### Paginación

```php
public array $pagination = [
    'products'       => 25,
    'inventory'      => 30,
    'kardex'         => 50,
    'movements'      => 25,
    'report_history' => 25,
];
```

### Buscador de productos

```php
public int $productSearchLimit = 30;
public int $productSearchMinChars = 2;
public int $productSearchMaxLength = 100;
public int $productSearchDebounceMs = 300;
public int $productSearchCacheTtlMs = 15000;
```

### Reportes

```php
public int $reportPreviewLimit = 100;
public int $reportSearchMaxLength = 100;
```

---

## Seguridad

El proyecto implementa varias medidas de seguridad:

- CSRF habilitado globalmente.
- Filtro de autenticación para rutas privadas.
- Filtro de permisos por módulo y acción.
- Hash de contraseñas mediante `password_hash()`.
- Límite de intentos de autenticación.
- Límite para acciones sensibles.
- Control de sesiones activas por usuario.
- Revocación de sesiones desde administración.
- Registro de auditoría de seguridad.
- Escape automático de HTML mediante Twig.
- Validación de formularios en backend.
- Prevención de stock negativo en movimientos de salida.

En producción también se recomienda:

- Utilizar HTTPS.
- Establecer `CI_ENVIRONMENT = production`.
- No publicar `.env`.
- Cambiar inmediatamente cualquier credencial inicial.
- Mantener `app/`, `writable/`, `vendor/` y `.env` fuera del DocumentRoot público.
- Mantener actualizadas las dependencias de Composer.

---

## Vistas Twig

Las vistas se encuentran en:

```text
app/Views/
```

La integración está centralizada en:

```text
app/Libraries/TwigView.php
```

En producción, Twig intenta utilizar caché en:

```text
writable/cache/twig/
```

Las funciones disponibles en las plantillas incluyen, entre otras:

```text
site_url()
base_url()
current_url()
auth_can()
csrf_field()
session_get()
session_has()
flash()
is_active()
asset_url()
```

---

## JavaScript

Los scripts se encuentran en:

```text
public/assets/js/
```

La aplicación separa componentes como:

```text
confirmation-modal.js
local-table-search.js
movement-form.js
product-search.js
remote-combobox.js
responsive-sidebar.js
responsive-tables.js
```

El archivo principal es:

```text
public/assets/js/app.js
```

---

## Agregar un nuevo módulo

La estructura recomendada es:

```text
app/Src/Modules/NuevoModulo/
├── Application/
├── Domain/
└── Infrastructure/
    ├── Http/
    ├── Persistence/
    └── Routing/
```

Después crea la clase que registra las rutas y agrégala en:

```text
app/Config/RouteModules.php
```

Ejemplo conceptual:

```php
public array $providers = [
    AuthenticationRoutes::class,
    InventoryRoutes::class,
    AdministrationRoutes::class,
    NuevoModuloRoutes::class,
];
```

De esta forma `Routes.php` permanece pequeño aunque el sistema crezca.

---

## Despliegue en Apache / cPanel

La opción recomendada es configurar el dominio o subdominio para que su **DocumentRoot apunte directamente a `public/`**.

Ejemplo:

```text
/home/usuario/apps/inventario/
├── app/
├── system/
├── vendor/
├── writable/
├── .env
└── public/            <- DocumentRoot
```

El navegador únicamente debe tener acceso público al contenido de `public/`.

Si el sistema se publica en una subruta, por ejemplo:

```text
https://midominio.com/inventario/
```

configura correctamente:

```ini
app.baseURL = 'https://midominio.com/inventario/'
```

Verifica además las reglas de `.htaccess` y que Apache tenga habilitado `mod_rewrite`.

No expongas directamente al navegador:

```text
.env
app/
vendor/
writable/
```

---

## Comandos útiles

Ejecutar servidor local:

```bash
php spark serve
```

Ejecutar migraciones:

```bash
php spark migrate
```

Revisar estado de migraciones:

```bash
php spark migrate:status
```

Revertir el último grupo de migraciones:

```bash
php spark migrate:rollback
```

Ejecutar seeders:

```bash
php spark db:seed AuthSeeder
php spark db:seed InventorySeeder
```

Listar rutas:

```bash
php spark routes
```

Limpiar caché:

```bash
php spark cache:clear
```

---

## Flujo básico de uso

1. Iniciar sesión.
2. Configurar tipos de producto y tipos de movimiento.
3. Registrar productos.
4. Registrar compras, ingresos, ventas, mermas u otras salidas desde Movimientos.
5. Consultar el stock actual en Inventario.
6. Revisar el kardex cuando se necesite conocer el historial de un producto.
7. Consultar Reportes y aplicar los filtros necesarios.
8. Exportar la información a Excel.
9. Administrar usuarios, roles y permisos cuando se requiera controlar el acceso a módulos.
