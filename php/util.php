<?php
require_once 'helpers.php';
require_once 'db.php';

/**
 * функция, возвращающая ресурс соединения
 * @param array $params массив параметров соединения
 *
 * @return false|mysqli ресурс соединения
 */
function connect_db($params)
{
    $con = mysqli_connect($params['host'], $params['user'], $params['password'], $params['db_name']);

    mysqli_set_charset($con, 'utf8');

    if (!$con) {
        die('Ошибка подключения: ' . mysqli_connect_error());
    }

    return $con;
}

/**
 * функция, проверяющая наличие значения в массиве по ключу
 * @param array $array массив
 * @param string $key ключ
 * @param string|integer $value значение
 *
 * @return boolean проверка наличия значения
 */
function isValueInArray($array, $key, $value)
{
    foreach ($array as $val) {
        if (isset($val[$key]) && $val[$key] === $value) {
            return true;
        }
    }
    return false;
}

/**
 * функция, возвращающая значение массива по ключу при его наличии
 * @param array $array массив
 * @param string $key ключ
 *
 * @return string|integer $value значение массива по ключу
 */
function getValue($array, $key)
{
    if (isset($array[$key])) {
        return $array[$key];
    }
    return "";
}

/**
 * функция, возвращающая количество задач в проекте
 * @param array $task_list массив задач
 * @param integer $project_id идентификатор проекта
 *
 * @return integer количество задач в проекте
 */
function countProjectTasks(array $task_list, $project_id)
{
    $count = 0;
    foreach ($task_list as $task) {
        if (isset($task['project_id']) && $task['project_id'] === $project_id) {
            $count++;
        }
    }
    return $count;
}

/**
 * функция, возвращающая массив проектов для конкретного пользователя
 * @param resource $con ресурс соединения
 * @param integer $user_id идентификатор пользователя
 *
 * @return array массив проектов для конкретного пользователя
 */
function getProjects($con, int $user_id)
{
    $sql = 'SELECT * FROM projects WHERE user_id = ?';
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $projects = mysqli_fetch_all($res, MYSQLI_ASSOC);

    return $projects;
}

/**
 * функция, возвращающая массив всех задач для конкретного пользователя
 * @param resource $con ресурс соединения
 * @param integer $user_id идентификатор пользователя
 *
 * @return array массив всех задач для конкретного пользователя
 */
function getTasksAll($con, int $user_id)
{
    $sql = 'SELECT * FROM tasks WHERE user_id = ?';
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $tasksAll = mysqli_fetch_all($res, MYSQLI_ASSOC);

    return $tasksAll;
}

/**
 * функция, возвращающая пользователя по email
 * @param resource $con ресурс соединения
 * @param string $email email пользователя
 *
 * @return array пользователь
 */
function getUser($con, $email)
{
    $sql = 'SELECT * FROM users WHERE email = ?';
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $users = mysqli_fetch_all($res, MYSQLI_ASSOC);
    $user = null;

    foreach ($users as $us) {
        $user = $us;
    }

    return $user;
}

/**
 * функция, возвращающая имя пользователя по идентификатору
 * @param resource $con ресурс соединения
 * @param integer $user_id идентификатор пользователя
 *
 * @return string имя пользователя по идентификатору
 */
function getUserName($con, $user_id)
{
    $sql = 'SELECT name FROM users WHERE id = ?';
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $users = mysqli_fetch_all($res, MYSQLI_ASSOC);
    $user_name = null;

    foreach ($users as $user) {
        $user_name = $user['name'];
    }

    return $user_name;
}

/**
 * функция, возвращающая значение поля формы
 * @param string $name имя поля формы
 *
 * @return string значение поля формы
 */
function getPostVal($name)
{
    return $_POST[$name] ?? '';
}

/**
 * функция для проверки заполненности и длины поля формы
 * @param string $name имя поля формы
 * @param integer $min минимальная длина введённого значения
 * @param integer $max максимальная длина введённого значения
 *
 * @return string текст ошибки
 */
function validateFilledAndLength($name, $min, $max)
{
    if (empty(trim($_POST[$name]))) {
        return 'Это поле должно быть заполнено';
    }

    $len = strlen(trim($_POST[$name]));

    if ($len < $min or $len > $max) {
        return 'Значение должно быть от ' . $min . ' до ' . $max . ' символов';
    }

    return "";
}

/**
 * функция, возвращающая класс для поля с ошибкой
 * @param array $errors массив ошибок
 * @param string $name имя поля
 *
 * @return string класс для поля с ошибкой
 */
function getClassError($errors, $name)
{
    if (isset($errors[$name])) {
        return 'form__input--error';
    }
    return '';
}

/*соединение с базой*/
$con = connect_db($params);
