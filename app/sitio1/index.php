<?php
session_start();

/* =========================
   CONFIGURACIÓN MARIA DB
   ========================= */

$host = "127.0.0.1";
$db   = "proyecto_web";
$user = "web_admin";
$pass = "Admin123";

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

} catch (PDOException $e) {

    die("Error de conexión con MariaDB.");

}


/* =========================
   CERRAR SESIÓN
   ========================= */

if (isset($_GET["logout"])) {

    session_unset();
    session_destroy();

    header("Location: index.php");
    exit;
}


$mensaje = "";
$error = "";


/* =========================
   LOGIN ADMIN
   ========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["login"])
) {

    $correo = trim($_POST["correo"]);
    $password = $_POST["password"];


    $stmt = $pdo->prepare(
        "SELECT id, nombre, correo, password, rol
         FROM usuarios
         WHERE correo = ?"
    );

    $stmt->execute([$correo]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);


    if (
        $usuario &&
        password_verify(
            $password,
            $usuario["password"]
        ) &&
        $usuario["rol"] === "admin"
    ) {

        session_regenerate_id(true);

        $_SESSION["id"] = $usuario["id"];
        $_SESSION["nombre"] = $usuario["nombre"];
        $_SESSION["correo"] = $usuario["correo"];
        $_SESSION["rol"] = $usuario["rol"];

        header("Location: index.php");
        exit;

    } else {

        $error =
            "Credenciales incorrectas o el usuario no es administrador.";

    }
}


/* =========================
   CREAR ADMIN
   =========================

   El registro público crea
   administradores.

   Si quieres que solamente
   un administrador pueda crear
   otros administradores, puedes
   eliminar el formulario público
   de abajo.
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["crear_admin"])
) {

    $nombre = trim($_POST["nombre"]);
    $correo = trim($_POST["correo"]);
    $password = $_POST["password"];


    if (
        $nombre === "" ||
        $correo === "" ||
        $password === ""
    ) {

        $error = "Todos los campos son obligatorios.";

    } elseif (
        !filter_var(
            $correo,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error = "El correo no es válido.";

    } elseif (strlen($password) < 6) {

        $error =
            "La contraseña debe tener al menos 6 caracteres.";

    } else {

        try {

            $hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            $stmt = $pdo->prepare(
                "INSERT INTO usuarios
                (nombre, correo, password, rol)
                VALUES (?, ?, ?, 'admin')"
            );

            $stmt->execute([
                $nombre,
                $correo,
                $hash
            ]);

            $mensaje =
                "Administrador creado correctamente.";

        } catch (PDOException $e) {

            $error =
                "No se pudo crear el administrador. El correo puede estar registrado.";

        }

    }
}


/* =========================
   TODAS LAS OPERACIONES
   SIGUIENTES REQUIEREN ADMIN
   ========================= */

$esAdmin =
    isset($_SESSION["id"]) &&
    isset($_SESSION["rol"]) &&
    $_SESSION["rol"] === "admin";


/* =========================
   CREAR USUARIO DESDE PANEL
   ========================= */

if (
    $esAdmin &&
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["crear_usuario"])
) {

    $nombre = trim($_POST["nombre"]);
    $correo = trim($_POST["correo"]);
    $password = $_POST["password"];
    $rol = $_POST["rol"];


    if (
        $nombre === "" ||
        $correo === "" ||
        $password === ""
    ) {

        $error = "Todos los campos son obligatorios.";

    } elseif (
        !filter_var(
            $correo,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error = "El correo no es válido.";

    } elseif (
        !in_array(
            $rol,
            ["admin", "lector"],
            true
        )
    ) {

        $error = "Rol no válido.";

    } elseif (strlen($password) < 6) {

        $error =
            "La contraseña debe tener al menos 6 caracteres.";

    } else {

        try {

            $hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            $stmt = $pdo->prepare(
                "INSERT INTO usuarios
                (nombre, correo, password, rol)
                VALUES (?, ?, ?, ?)"
            );

            $stmt->execute([
                $nombre,
                $correo,
                $hash,
                $rol
            ]);

            $mensaje =
                "Usuario creado correctamente.";

        } catch (PDOException $e) {

            $error =
                "No se pudo crear el usuario. El correo puede estar registrado.";

        }
    }
}


/* =========================
   ACTUALIZAR USUARIO
   ========================= */

if (
    $esAdmin &&
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["actualizar"])
) {

    $id = (int)$_POST["id"];
    $nombre = trim($_POST["nombre"]);
    $correo = trim($_POST["correo"]);
    $rol = $_POST["rol"];


    if (
        $nombre === "" ||
        $correo === ""
    ) {

        $error =
            "Nombre y correo son obligatorios.";

    } elseif (
        !filter_var(
            $correo,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            "El correo no es válido.";

    } elseif (
        !in_array(
            $rol,
            ["admin", "lector"],
            true
        )
    ) {

        $error = "Rol no válido.";

    } else {

        /*
         * Evitamos que el administrador
         * se quite a sí mismo el rol.
         */

        if (
            $id === (int)$_SESSION["id"] &&
            $rol !== "admin"
        ) {

            $error =
                "No puedes cambiar tu propia cuenta a lector.";

        } else {

            try {

                $stmt = $pdo->prepare(
                    "UPDATE usuarios
                     SET nombre = ?,
                         correo = ?,
                         rol = ?
                     WHERE id = ?"
                );

                $stmt->execute([
                    $nombre,
                    $correo,
                    $rol,
                    $id
                ]);

                $mensaje =
                    "Usuario actualizado correctamente.";

            } catch (PDOException $e) {

                $error =
                    "No se pudo actualizar el usuario.";

            }
        }
    }
}


/* =========================
   ELIMINAR USUARIO
   ========================= */

if (
    $esAdmin &&
    isset($_GET["delete"])
) {

    $id = (int)$_GET["delete"];


    /*
     * No permitimos eliminar
     * la propia cuenta.
     */

    if ($id === (int)$_SESSION["id"]) {

        $error =
            "No puedes eliminar tu propia cuenta.";

    } else {

        try {

            $stmt = $pdo->prepare(
                "DELETE FROM usuarios
                 WHERE id = ?"
            );

            $stmt->execute([$id]);

            $mensaje =
                "Usuario eliminado correctamente.";

        } catch (PDOException $e) {

            $error =
                "No se pudo eliminar el usuario.";

        }
    }
}


/* =========================
   USUARIO PARA EDITAR
   ========================= */

$editar = null;


if (
    $esAdmin &&
    isset($_GET["edit"])
) {

    $id = (int)$_GET["edit"];


    $stmt = $pdo->prepare(
        "SELECT id, nombre, correo, rol
         FROM usuarios
         WHERE id = ?"
    );

    $stmt->execute([$id]);

    $editar =
        $stmt->fetch(PDO::FETCH_ASSOC);
}


/* =========================
   LISTAR USUARIOS
   =========================

   SOLO ADMIN
*/

$usuarios = [];


if ($esAdmin) {

    $stmt = $pdo->query(
        "SELECT id, nombre, correo, rol
         FROM usuarios
         ORDER BY id"
    );

    $usuarios =
        $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Panel de Administradores</title>


    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 40px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .card {
            background: white;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        input,
        select {
            display: block;
            width: 300px;
            max-width: 100%;
            padding: 10px;
            margin: 10px 0;
            box-sizing: border-box;
        }

        button {
            padding: 10px 18px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #e5e7eb;
        }

        .error {
            color: #b91c1c;
            background: #fee2e2;
            padding: 10px;
        }

        .success {
            color: #166534;
            background: #dcfce7;
            padding: 10px;
        }

        .logout {
            color: #dc2626;
        }

        .danger {
            color: #dc2626;
        }

    </style>

</head>


<body>

<div class="container">


    <div class="card">

        <h1>Panel de Administradores</h1>

        <p>
            Sitio 2 - Administración del sistema
        </p>

    </div>


    <?php if ($mensaje !== ""): ?>

        <div class="card success">
            <?= htmlspecialchars($mensaje) ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="card error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <?php if (!$esAdmin): ?>


        <!-- =========================
             LOGIN
             ========================= -->

        <div class="card">

            <h2>Iniciar sesión como administrador</h2>

            <form method="POST">

                <input
                    type="email"
                    name="correo"
                    placeholder="Correo"
                    required
                >

                <input
                    type="password"
                    name="password"
                    placeholder="Contraseña"
                    required
                >

                <button
                    type="submit"
                    name="login"
                >
                    Iniciar sesión
                </button>

            </form>

        </div>


        <!-- =========================
             REGISTRAR ADMIN
             ========================= -->

        <div class="card">

            <h2>Crear cuenta de administrador</h2>

            <form method="POST">

                <input
                    type="text"
                    name="nombre"
                    placeholder="Nombre completo"
                    required
                >

                <input
                    type="email"
                    name="correo"
                    placeholder="Correo"
                    required
                >

                <input
                    type="password"
                    name="password"
                    placeholder="Contraseña"
                    minlength="6"
                    required
                >

                <button
                    type="submit"
                    name="crear_admin"
                >
                    Crear administrador
                </button>

            </form>

        </div>


    <?php else: ?>


        <!-- =========================
             ADMIN LOGUEADO
             ========================= -->

        <div class="card">

            <h2>
                Bienvenido,
                <?= htmlspecialchars($_SESSION["nombre"]) ?>
            </h2>

            <p>
                Correo:
                <?= htmlspecialchars($_SESSION["correo"]) ?>
            </p>

            <p>
                Rol:
                <strong>ADMINISTRADOR</strong>
            </p>

            <p>
                <a
                    class="logout"
                    href="?logout=1"
                >
                    Cerrar sesión
                </a>
            </p>

        </div>


        <!-- =========================
             CREAR USUARIO
             ========================= -->

        <div class="card">

            <h2>Crear usuario</h2>

            <form method="POST">

                <input
                    type="text"
                    name="nombre"
                    placeholder="Nombre completo"
                    required
                >

                <input
                    type="email"
                    name="correo"
                    placeholder="Correo"
                    required
                >

                <input
                    type="password"
                    name="password"
                    placeholder="Contraseña"
                    minlength="6"
                    required
                >

                <select name="rol" required>

                    <option value="lector">
                        Lector
                    </option>

                    <option value="admin">
                        Administrador
                    </option>

                </select>

                <button
                    type="submit"
                    name="crear_usuario"
                >
                    Crear usuario
                </button>

            </form>

        </div>


        <!-- =========================
             EDITAR
             ========================= -->

        <?php if ($editar): ?>

            <div class="card">

                <h2>Editar usuario</h2>

                <form method="POST">

                    <input
                        type="hidden"
                        name="id"
                        value="<?= htmlspecialchars($editar["id"]) ?>"
                    >

                    <label>
                        Nombre
                    </label>

                    <input
                        type="text"
                        name="nombre"
                        value="<?= htmlspecialchars($editar["nombre"]) ?>"
                        required
                    >

                    <label>
                        Correo
                    </label>

                    <input
                        type="email"
                        name="correo"
                        value="<?= htmlspecialchars($editar["correo"]) ?>"
                        required
                    >

                    <label>
                        Rol
                    </label>

                    <select name="rol">

                        <option
                            value="lector"
                            <?= $editar["rol"] === "lector"
                                ? "selected"
                                : "" ?>
                        >
                            Lector
                        </option>

                        <option
                            value="admin"
                            <?= $editar["rol"] === "admin"
                                ? "selected"
                                : "" ?>
                        >
                            Administrador
                        </option>

                    </select>

                    <button
                        type="submit"
                        name="actualizar"
                    >
                        Guardar cambios
                    </button>

                </form>

            </div>

        <?php endif; ?>


        <!-- =========================
             TABLA
             ========================= -->

        <div class="card">

            <h2>Usuarios registrados</h2>

            <table>

                <tr>

                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Acciones</th>

                </tr>


                <?php foreach ($usuarios as $usuario): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($usuario["id"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($usuario["nombre"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($usuario["correo"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($usuario["rol"]) ?>
                        </td>

                        <td>

                            <a
                                href="?edit=<?= $usuario["id"] ?>"
                            >
                                Editar
                            </a>


                            <?php if (
                                $usuario["id"]
                                != $_SESSION["id"]
                            ): ?>

                                |

                                <a
                                    class="danger"
                                    href="?delete=<?= $usuario["id"] ?>"
                                    onclick="return confirm('¿Seguro que quieres eliminar este usuario?');"
                                >
                                    Eliminar
                                </a>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </table>

        </div>


    <?php endif; ?>


</div>

</body>

</html>