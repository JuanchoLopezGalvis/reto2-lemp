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

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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


/* =========================
   VARIABLES
   ========================= */

$mensaje = "";
$error = "";


/* =========================
   CREAR CUENTA DE LECTOR
   ========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["crear_lector"])) {

    $nombre = trim($_POST["nombre"]);
    $correo = trim($_POST["correo"]);
    $password = $_POST["password"];

    if ($nombre === "" || $correo === "" || $password === "") {

        $error = "Todos los campos son obligatorios.";

    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {

        $error = "El correo no es válido.";

    } elseif (strlen($password) < 6) {

        $error = "La contraseña debe tener al menos 6 caracteres.";

    } else {

        try {

            $hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare(
                "INSERT INTO usuarios
                (nombre, correo, password, rol)
                VALUES (?, ?, ?, 'lector')"
            );

            $stmt->execute([
                $nombre,
                $correo,
                $hash
            ]);

            $mensaje = "Cuenta de lector creada correctamente.";

        } catch (PDOException $e) {

            $error = "No se pudo crear la cuenta. El correo puede estar registrado.";

        }
    }
}


/* =========================
   INICIAR SESIÓN
   ========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["login"])) {

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
        password_verify($password, $usuario["password"]) &&
        $usuario["rol"] === "lector"
    ) {

        session_regenerate_id(true);

        $_SESSION["id"] = $usuario["id"];
        $_SESSION["nombre"] = $usuario["nombre"];
        $_SESSION["correo"] = $usuario["correo"];
        $_SESSION["rol"] = $usuario["rol"];

        header("Location: index.php");
        exit;

    } else {

        $error = "Credenciales incorrectas o el usuario no es lector.";

    }
}


/* =========================
   CREAR OTRO LECTOR
   =========================
   
   SOLO puede hacerlo un lector
   que ya haya iniciado sesión.
*/

if (
    isset($_SESSION["id"]) &&
    $_SESSION["rol"] === "lector" &&
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["crear_lector_sesion"])
) {

    $nombre = trim($_POST["nombre"]);
    $correo = trim($_POST["correo"]);
    $password = $_POST["password"];

    if ($nombre === "" || $correo === "" || $password === "") {

        $error = "Todos los campos son obligatorios.";

    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {

        $error = "El correo no es válido.";

    } elseif (strlen($password) < 6) {

        $error = "La contraseña debe tener al menos 6 caracteres.";

    } else {

        try {

            $hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare(
                "INSERT INTO usuarios
                (nombre, correo, password, rol)
                VALUES (?, ?, ?, 'lector')"
            );

            $stmt->execute([
                $nombre,
                $correo,
                $hash
            ]);

            $mensaje = "Nuevo lector creado correctamente.";

        } catch (PDOException $e) {

            $error = "No se pudo crear el lector. El correo puede estar registrado.";

        }
    }
}


/* =========================
   OBTENER USUARIOS
   =========================

   SOLO si hay sesión de lector.
*/

$usuarios = [];

if (
    isset($_SESSION["id"]) &&
    $_SESSION["rol"] === "lector"
) {

    $stmt = $pdo->query(
        "SELECT id, nombre, correo, rol
         FROM usuarios
         ORDER BY id"
    );

    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Portal de Lectores</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 40px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        .card {
            background: white;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        input {
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

        th, td {
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

    </style>

</head>

<body>

<div class="container">

    <div class="card">

        <h1>Portal de Lectores</h1>

        <p>
            Sitio 1 - Sistema de consulta de usuarios
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


    <?php if (!isset($_SESSION["id"])): ?>

        <!-- =========================
             LOGIN
             ========================= -->

        <div class="card">

            <h2>Iniciar sesión</h2>

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
             REGISTRO
             ========================= -->

        <div class="card">

            <h2>Crear cuenta de lector</h2>

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
                    name="crear_lector"
                >
                    Crear cuenta
                </button>

            </form>

        </div>


    <?php else: ?>


        <!-- =========================
             USUARIO LOGUEADO
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
                <strong>LECTOR</strong>
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
             CREAR LECTOR
             ========================= -->

        <div class="card">

            <h2>Crear nuevo lector</h2>

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
                    name="crear_lector_sesion"
                >
                    Crear lector
                </button>

            </form>

        </div>


        <!-- =========================
             LISTA DE USUARIOS
             ========================= -->

        <div class="card">

            <h2>Usuarios registrados</h2>

            <table>

                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
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

                    </tr>

                <?php endforeach; ?>

            </table>

        </div>

    <?php endif; ?>

</div>

</body>

</html>