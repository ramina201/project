<?php
require_once 'util.php';

session_start();

/*объявление переменных*/
if (isset($_SESSION['user'])) {
    $user_id = $_SESSION['user'];
    $projects = getProjects($con, $user_id);
    $tasksAll = array_reverse(getTasksAll($con, $user_id));
    $user_name = getUserName($con, $user_id);
    $errors = getErrors($projects);
}

/**
 * функция для добавления проекта в БД
 * @param resource $con ресурс соединения
 * @param integer $user_id идентификатор пользователя
 * @param string $project_name название проекта
 */
function addProject($con, int $user_id, string $project_name)
{

    $parameters = [$user_id, $project_name];
    $sql = 'INSERT INTO projects (user_id, name) VALUES (?, ?)';

    $stmt = db_get_prepare_stmt($con, $sql, $parameters);
    mysqli_stmt_execute($stmt);
}

/**
 * функция для валидации имени проекта
 * @param array $projects массив проектов
 * @param integer $min минимальная длина названия проекта
 * @param integer $max максимальная длина названия проекта
 *
 * @return string текст ошибки
 */
function validateName($projects, $min, $max)
{
    $name = trim(getPostVal('name'));

    if (empty($name)) {
        return 'Это поле должно быть заполнено';
    }

    $len = strlen($name);

    if ($len < $min or $len > $max) {
        return 'Значение должно быть от ' . $min . ' до ' . $max . ' символов';
    }

    if (isValueInArray($projects, 'name', $name)) {
        return 'Проект с таким названием уже существует';
    }

    return "";
}

/**
 * функция, возвращающая массив ошибок
 * @param array $projects массив проектов
 * @return array массив ошибок
 */
function getErrors($projects)
{
    $errors = [];

    $rules = [
        'name' => function ($projects) {
            return validateName($projects, 1, 255);
        }
    ];

    foreach ($_POST as $key => $value) {

        if (isset($rules[$key])) {
            $rule = $rules[$key];
            $errors[$key] = $rule($projects);
        }
    }

    return array_filter($errors);
}

/**
 * функция для обработки формы добавления проекта
 * @param resource $con ресурс соединения
 * @param integer $user_id идентификатор пользователя
 * @param array $errors массив ошибок
 */
function processingFormAddProject($con, $user_id, $errors)
{
    $project_name = getPostVal('name');

    if (!count($errors)) {
        addProject($con, $user_id, $project_name);
        header('Location: ../index.php');
    }
}

/*проверка отправки формы*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    processingFormAddProject($con, $user_id, $errors);
}


if (isset($_SESSION['user'])) {
    
} else {
    header('Location: ../index.php');
    exit();
}
