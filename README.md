# SGRUP — Sistema de Gestión y Seguimiento de Proyectos

Aplicación web para el control **técnico, financiero y documental** de los proyectos de investigación y extensión, convenios y contratos con alcaldías, gobernación y entidades privadas.

> **Estado actual: versión de demostración (diseño).**
> Los datos son de ejemplo y se generan en memoria (`app/Support/DemoData.php`); no se leen de la base de datos de negocio. El inicio de sesión consiste en elegir un usuario de prueba. Ver [Limitaciones de la demo](#limitaciones-de-la-demo).

---

## Roles y módulos

Cada rol entra a su propio módulo, protegido por el middleware `role:<rol>`.

| Rol | Prefijo de URL | Qué hace |
|---|---|---|
| **Investigador / Coordinador** (`investigador`) | `/investigador` | Carga el avance mensual, solicita compras, sube documentos, consulta cronograma, alertas y las pestañas de su proyecto. |
| **Vicerrectoría / Subdirección** (`vicerrectoria`) | `/vicerrectoria` | Aprueba compras, avances, traslados y proyectos nuevos. Hace seguimiento (curva S, cronograma, reportes), avanza fases, certifica y liquida proyectos. |
| **Área financiera** (`financiero`) | `/financiera` | Expide CDP, devuelve o valida compras, registra pagos, controla el presupuesto por rubro y valida soportes (paquete RUP). |
| **Administrador** (`admin`) | `/administracion` | Gestiona usuarios y roles, rubros y reglas del sistema, consulta y exporta la auditoría, y restablece los datos de la demo. |

### Entidades

Vicerrectoría y el Área financiera administran en **Entidades** (`/entidades`) el catálogo de entidades contratantes y aliadas: sus datos (NIT, tipo, municipio, representante legal, contacto) y sus documentos legales (RUT, existencia y representación legal, certificación bancaria…). Cada entidad tiene un checklist de documentos obligatorios según sea pública o privada. Al crear o editar un proyecto, la entidad se busca y se elige de este catálogo.

### Pestañas de un proyecto

Investigador y Vicerrectoría comparten la vista de proyecto (`Vic\ProjectController`): resumen, objetivos y productos, presupuesto y traslados, avances, compras, documentos, trazabilidad y, solo para Vicerrectoría, la certificación.

---

## Tecnologías

- **PHP 8.3+** y **Laravel 13**
- **Blade** para las vistas (layouts, componentes `x-*` y partials)
- **Vite** con **Tailwind CSS 4** para los recursos front-end
- **SQLite** por defecto, que en la demo solo guarda sesiones, caché y colas (PostgreSQL previsto para producción)
- **PHPUnit** para pruebas y **Pint** para formato de código

---

## Requisitos

- PHP 8.3 o superior, con las extensiones `pdo_sqlite`, `mbstring` y `openssl`
- Composer 2
- Node.js 20 o superior, con npm

---

## Instalación

```bash
git clone <url-del-repositorio> sgrup
cd sgrup
composer run setup
```

`composer run setup` ejecuta, en orden:

1. `composer install`
2. copia `.env.example` → `.env` (si no existe)
3. `php artisan key:generate`
4. `php artisan migrate --force` (crea las tablas de sesiones, caché y colas)
5. `npm install` y `npm run build`

Si prefieres hacerlo a mano:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # en Windows: type nul > database\database.sqlite
php artisan migrate
npm install
npm run build
```

---

## Ejecutar en local

```bash
composer run dev
```

Abre <http://localhost:8000>. Te redirige a `/login`, donde eliges un usuario de prueba y entras con su rol.

Si prefieres levantar los procesos por separado:

```bash
php artisan serve   # servidor PHP
npm run dev         # Vite con recarga en caliente
```

### Usuarios de la demo

| Usuario | Rol |
|---|---|
| Laura Méndez | Investigador / Coordinador |
| Dra. Marcela Trujillo | Vicerrectoría |
| Carlos Rojas | Área financiera |
| Daniel Rojas | Administrador |

No hace falta contraseña: se elige el usuario en la pantalla de inicio.

---

## Cómo funciona la demo

- **Datos base**: `App\Support\DemoData` construye en memoria usuarios, proyectos, rubros, compras, avances, alertas y la curva S. Usa modelos Eloquent sin guardar, para que las vistas no cambien cuando se conecte la base de datos.
- **Cambios hechos en la demo** (aprobaciones, CDP, pagos, usuarios creados…): se guardan con `App\Support\DemoState` en la **caché** de la aplicación. Así, lo que hace un rol lo ve el siguiente al cambiar de usuario.
- **Restablecer**: el Administrador tiene el botón *Restablecer datos demo*. Desde la terminal también puedes hacerlo con `php artisan cache:clear`.
- **Sesión**: `App\Http\Middleware\DemoAuth` toma de la sesión el usuario elegido y lo deja disponible en `auth()->user()`.

---

## Estructura del proyecto

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php        # Inicio de sesión de la demo
│   │   ├── Adm/AdminController.php   # Administración
│   │   ├── Fin/FinanceController.php # Área financiera
│   │   ├── Inv/InvestigatorController.php
│   │   └── Vic/                      # Panel, aprobaciones, proyectos, compras, monitoreo
│   └── Middleware/
│       ├── DemoAuth.php              # Usuario de la demo desde la sesión
│       └── EnsureRole.php            # Restringe rutas por rol
├── Models/                           # Project, BudgetLine, PurchaseRequest, MonthlyProgress…
├── Support/                          # Lógica de la demo y de dominio
│   ├── DemoData.php / DemoState.php  # Datos de ejemplo y estado persistido en caché
│   ├── FinanceStore.php              # CDP, pagos, presupuesto, documentos
│   ├── ApprovalInbox.php             # Bandeja de aprobaciones
│   ├── ProjectTracking.php           # Avances, productos, trazabilidad
│   ├── AdminStore.php                # Usuarios, parámetros, auditoría
│   └── SCurve.php                    # Cálculo de la curva S
└── helpers.php
database/
├── migrations/                       # Incluye el esquema de negocio (create_sigep_tables)
└── seeders/DatabaseSeeder.php        # Los mismos datos de ejemplo, para cuando se use la BD
resources/views/
├── layouts/                          # app (general) y print (documentos imprimibles)
├── components/                       # <x-icon>, <x-alert-item>, <x-s-curve>
├── partials/
├── auth/  adm/  fin/  inv/  vic/     # Vistas por módulo
routes/web.php                        # Todas las rutas, agrupadas por rol
```

---

## Comandos útiles

| Comando | Para qué sirve |
|---|---|
| `composer run dev` | Levanta el entorno de desarrollo |
| `composer run test` | Ejecuta las pruebas |
| `./vendor/bin/pint` | Formatea el código PHP |
| `php artisan route:list` | Lista todas las rutas |
| `php artisan cache:clear` | Restablece el estado de la demo |
| `php artisan migrate:fresh --seed` | Crea el esquema de negocio con datos de ejemplo (la demo todavía no lo usa) |

---

## Limitaciones de la demo

- **No hay autenticación real.** Antes de producción hay que reemplazar el inicio de sesión por correo y contraseña y quitar `DemoAuth`.
- **Los datos de negocio no salen de la base de datos.** `Route::bind('project', …)` y los *stores* leen de `DemoData`. Falta conectar los modelos a la base de datos (PostgreSQL) usando las migraciones y el seeder existentes.
- **Los cambios viven en la caché.** Se pierden al limpiarla y son compartidos por todos los que usan la misma instancia.
- **Las alertas son una lista fija de ejemplo**; todavía no se calculan a partir de los datos.
- **Cambiar de fase y liquidar** solo queda anotado en la trazabilidad; el proyecto no cambia de estado.
- Las pruebas (`tests/Feature`, 21 en total) cubren las pantallas de cada rol y los flujos principales de la demo.

---

## Licencia

Proyecto académico del semillero de investigación. Define aquí la licencia del proyecto.
