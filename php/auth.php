<?php
require_once 'util.php';

session_start();

$errors = getErrors($con);

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

    $len = strlen(trim($email));

    if ($len < $min or $len > $max) {
        return 'Значение должно быть от ' . $min . ' до ' . $max . ' символов';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'E-mail введён некорректно';
    }

    if (empty(getUser($con, $email))) {
        return 'Неверный email';
    }

    return "";
}

/**
 * функция для валидации пароля
 * @param resource $con ресурс соединения
 * @param string $name имя поля формы
 * @param integer $min минимальная длина введённого значения
 * @param integer $max максимальная длина введённого значения
 *
 * @return string текст ошибки
 */
function validatePassword($con, $name, $min, $max)
{
    $password = getPostVal($name);

    if (empty($password)) {
        return 'Это поле должно быть заполнено';
    }

    $len = strlen(trim($_POST[$name]));

    if ($len < $min or $len > $max) {
        return 'Значение должно быть от ' . $min . ' до ' . $max . ' символов';
    }

    $user = getUser($con, getPostVal('email'));

    if (!empty($user) && !password_verify($password, $user['password'])) {
        return 'Неверный пароль';
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
            return validateEmail($con, 'email', 1, 128);
        },

        'password' => function ($con) {
            return validatePassword($con, 'password', 1, 64);
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
 * функция, формирующая сообщение об ошибках
 * @param array $errors массив ошибок
 *
 * @return string текст сообщения об ошибках
 */
function getErrorMessage($errors)
{
    if (getValue($errors, 'email') === 'Неверный email' || getValue($errors, 'password') === 'Неверный пароль') {
        return 'Вы ввели неверный email/пароль';
    } else {
        return 'Пожалуйста, исправьте ошибки в форме';
    }
}

/**
 * функция для обработки формы аутентификации
 * @param resource $con ресурс соединения
 * @param array $errors массив ошибок
 */
function processingFormAuth($con, $errors)
{
    $email = getPostVal('email');

    if (!count($errors)) {
        session_start();
        $_SESSION['user'] = getUser($con, $email)['id'];
 
    }
}

/*проверка отправки формы*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_abort();
    processingFormAuth($con, $errors);
}

if (!isset($_SESSION['user'])) {

} else {
    header('Location: ../index.php');
}
