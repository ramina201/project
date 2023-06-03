<?php
require_once 'util.php';

session_start();
$errors = getErrors($con);
/*объявление переменных*/
if (isset($_SESSION['user'])) {
    $user_id = $_SESSION['user'];
    $projects = getProjects($con, $user_id);
    $tasksAll = array_reverse(getTasksAll($con, $user_id));
    $user_name = getUserName($con, $user_id);
    $errors = getErrors($projects);
}

/**
 * функция для добавления задачи в БД
 * @param resource $con ресурс соединения
 * @param integer $user_id идентификатор пользователя
 * @param string $task_name название задачи
 * @param integer $project_id идентификатор проекта
 * @param string $due_date дата выполнения
 * @param string $file_path путь к файлу
 */
function addTask($con, int $user_id, string $task_name, int $project_id, string $due_date, string $file_path)
{
    $parameters = [$user_id, $task_name, $project_id];
    $sql = 'INSERT INTO tasks (user_id, name, project_id';

    if (!empty($due_date)) {
        $parameters[] = $due_date;
        $sql .= ', due_date';
    }
    if (!empty($file_path)) {
        $parameters[] = $file_path;
        $sql .= ', file_path';
    }

    $sql .= ') VALUES (';
    for ($i = 0; $i < count($parameters); $i++) {
        $sql .= '?, ';
    }
    $sql = substr($sql, 0, -2) . ')';

    $stmt = db_get_prepare_stmt($con, $sql, $parameters);
    mysqli_stmt_execute($stmt);
}

/**
 * функция, возвращающая имя файла из формы
 * @param string $name имя поля формы
 *
 * @return string имя файла
 */
function getFilesVal($name)
{
    if (isset ($_FILES[$name])) {
        return $_FILES[$name]['name'] ?? '';
    }
    return "";
}

/**
 * функция для валидации проекта
 * @param array $projects массив проектов
 *
 * @return string текст ошибки
 */
function validateRealProject($projects)
{
    if (!isValueInArray($projects, 'id', (int)$_POST['project'])) {
        return 'Проект должен быть реально существующим';
    }
    return "";
}

/**
 * функция для валидации даты
 * @return string текст ошибки
 */
function validateDate()
{
    $date = $_POST['date'];
    if (!empty($date)) {
        if (is_date_valid($date)) {
            $cur_date = time();
            $task_date = strtotime($date);
            if (floor(($cur_date - $task_date) / 3600) >= 24) {
                return 'Дата должна быть больше или равна текущей';
            }
        } else {
            return 'Дата должна быть в формате ГГГГ-ММ-ДД';
        }
    }
    return "";
}

/**
 * функция для проверки ошибки загрузки файла
 * @param string $name имя поля формы
 *
 * @return string текст ошибки
 */
function errorsFile($name)
{
    if (isset ($_FILES[$name]) && $_FILES[$name]['error'] > 0) {
        return 'Ошибка загрузки файла';
    }
    return "";
}

/**
 * функция для добавления атрибута выбранному селекту
 * @param string $name имя поля формы
 * @param integer $id идентификатор
 *
 * @return string атрибут
 */
function getSelected($name, $id)
{
    if (isset($_POST[$name]) && (int)getPostVal($name) === $id) {
        return 'selected';
    }
    return "";
}

/**
 * функция, возвращающая массив ошибок
 * @param array $projects массив проектов
 *
 * @return array массив ошибок
 */
function getErrors($projects)
{
    $errors = [];

    $rules = [
        'name' => function () {
            return validateFilledAndLength('name', 1, 255);
        },

        'project' => function ($projects) {
            return validateRealProject($projects);
        },

        'date' => function () {
            return validateDate();
        },

        'file' => function () {
            return errorsFile('file');
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
 * функция для обработки формы добавления задачи
 * @param resource $con ресурс соединения
 * @param integer $user_id идентификатор пользователя
 * @param array $errors массив ошибок
 */
function processingFormAddTask($con, $user_id, $errors)
{
    $task_name = getPostVal('name');
    $project_id = getPostVal('project');
    $due_date = getPostVal('date');
    $file_name = getFilesVal('file');
    $file_path = '';

    if (!count($errors)) {
        if (!empty($file_name) && isset($_FILES['file'])) {
            if (move_uploaded_file($_FILES['file']['tmp_name'], $_SERVER['DOCUMENT_ROOT'] . '/' . $file_name)) {
                $file_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $file_name;
            }
        }
        addTask($con, $user_id, $task_name, $project_id, $due_date, $file_path);
        header('Location: ../index.php');
    }
}

/*проверка отправки формы*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    processingFormAddTask($con, $user_id, $errors);
}

if (isset($_SESSION['user'])) {
    
} else {
    header('Location: ../index.php');
    exit();
}
