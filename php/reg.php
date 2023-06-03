<?php
require_once 'util.php';

session_start();

$errors = getErrors($con);

/**
 * функция для добавления пользователя
 * @param resource $con ресурс соединения
 * @param string $email email пользователя
 * @param string $name имя пользователя
 * @param string $password пароль пользователя
 */
function addUser($con, string $email, string $name, string $password)
{
    $parameters = [$email, $name, $password];
    $sql = 'INSERT INTO users (email, name, password) VALUES (?, ?, ?)';
    $stmt = db_get_prepare_stmt($con, $sql, $parameters);
    mysqli_stmt_execute($stmt);
}

/**
 * функция для валидации email
 * @param resource $con ресурс соединения
 * @param string $name имя поля формы
 * @param integer $min минимальная длина введённого значения
 * @param integer $max максимальная длина введённого значения
 *
 * @return string текст ошибки
 */
function validateEmail($con, $name, $min, $max)
{
    $email = getPostVal($name);

    if (empty($email)) {
        return 'Это поле должно быть заполнено';
    }

    $len = strlen(trim($_POST[$name]));

    if ($len < $min or $len > $max) {
        return 'Значение должно быть от ' . $min . ' до ' . $max . ' символов';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'E-mail введён некорректно';
    }

    if (!empty(getUser($con, $email))) {
        return 'E-mail уже используется другим пользователем';
    }

    return "";
}

/**
 * функция, возвращающая массив ошибок
 * @param resource $con ресурс соединения
 *
 * @return array массив ошибок
 */
function getErrors($con)
{
    $errors = [];

    $rules = [
        'email' => function ($con) {
            return validateEmail($con, 'email', 1, 64);
        },

        'password' => function () {
            return validateFilledAndLength('password', 1, 64);
        },

        'name' => function () {
            return validateFilledAndLength('name', 1, 255);
        },
    ];

    foreach ($_POST as $key => $value) {

        if (isset($rules[$key])) {
            $rule = $rules[$key];
            $errors[$key] = $rule($con);
        }
    }

    return array_filter($errors);
}

/**
 * функция для обработки формы регистрации
 * @param resource $con ресурс соединения
 * @param array $errors массив ошибок
 */
function processingFormRegister($con, $errors)
{
    $email = getPostVal('email');
    $password = getPostVal('password');
    $user_name = getPostVal('name');

    if (!count($errors)) {
        $password = password_hash($password, PASSWORD_DEFAULT);
        addUser($con, $email, $user_name, $password);
        session_start();
        $_SESSION['user'] = getUser($con, $email)['id'];
    }
}

/*проверка отправки формы*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_abort();
    processingFormRegister($con, $errors);
}


if (!isset($_SESSION['user'])) {
    
} else {
    header('Location: ../index.php');
}
