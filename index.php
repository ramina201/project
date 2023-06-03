<?php
// показывать или нет выполненные задачи
$show_complete_tasks = rand(0, 1);
?>

<?php
require_once 'php/util.php';
//require_once 'php/notify.php';
session_start();
/**
 * функция, проверяющая, осталось ли до выполнения задачи менее суток
 * @param string $date дата выполнения задачи
 *
 * @return integer разница между датами в часах
 */
function isDateDiffLess($date)
{
    $cur_date = time();
    $task_date = strtotime($date);
    $diff = floor(($task_date - $cur_date) / 3600);

    return $diff <= 24;
}

/**
 * функция, возвращающая url
 * @param string $file_path путь к файлу
 *
 * @return string url файла
 */
function getUrl($file_path)
{
    return str_replace($_SERVER['DOCUMENT_ROOT'], 'http://' . $_SERVER['HTTP_HOST'], $file_path);
}

/**
 * функция, возвращающая массив задач для конкретного пользователя и проекта
 * @param resource $con ресурс соединения
 * @param integer $user_id идентификатор пользователя
 * @param integer $project_id идентификатор проекта
 *
 * @return array массив задач для конкретного пользователя и проекта
 */
function getTasks($con, int $user_id, int $project_id = null)
{
    $parameters = [];
    $sql = 'SELECT * FROM tasks WHERE user_id = ?';
    $parameters[] = $user_id;
    if (!is_null($project_id)) {
        $sql .= " and project_id = ?";
        $parameters[] = $project_id;
    }

    $stmt = db_get_prepare_stmt($con, $sql, $parameters);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $tasks = mysqli_fetch_all($res, MYSQLI_ASSOC);

    return $tasks;
}

/**
 * функция, возвращающая массив задач из строки поиска
 * @param resource $con ресурс соединения
 * @param string $search текст строки поиска
 * @param integer $user_id идентификатор пользователя
 *
 * @return array массив задач из строки поиска
 */
function getSearchTasks($con, $search, int $user_id)
{
    $sql = 'SELECT * FROM tasks WHERE user_id = ? AND MATCH(name) AGAINST (? IN BOOLEAN MODE)';

    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'is', $user_id, $search);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $tasks = mysqli_fetch_all($res, MYSQLI_ASSOC);

    return $tasks;
}

/**
 * функция, возвращающая задачу по идентификатору
 * @param resource $con ресурс соединения
 * @param integer $task_id идентификатор задачи
 *
 * @return array массив задач по идентификатору
 */
function getTaskWhereId($con, int $task_id)
{
    $sql = 'SELECT * FROM tasks WHERE id = ?';
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $task_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $tasks = mysqli_fetch_all($res, MYSQLI_ASSOC);
    $task = null;

    foreach ($tasks as $value) {
        $task = $value;
    }

    return $task;
}

/**
 * функция, инвертирующая статус задачи
 * @param resource $con ресурс соединения
 * @param array $task массив задач
 */
function changeStatus($con, $task)
{
    $status = 1 - getValue($task, 'status');

    $parameters = [$status, getValue($task, 'id')];
    $sql = 'UPDATE tasks SET status = ? WHERE id = ?';

    $stmt = db_get_prepare_stmt($con, $sql, $parameters);
    mysqli_stmt_execute($stmt);
}

/**
 * функция для добавления параметра к строке запроса
 * @param string $name_params имя параметра из массива $_GET
 * @param string $value_params значение параметра
 *
 * @return string url с новым параметром
 */
function getNewURL($name_params, $value_params)
{
    $params = $_GET;
    $params[$name_params] = $value_params;

    return pathinfo(__FILE__, PATHINFO_BASENAME) . '?' . http_build_query($params);
}

/**
 * функция, возвращающая массив задач на сегодня
 * @param array $tasks массив задач
 *
 * @return array массив задач на сегодня
 */
function getTasksToday($tasks)
{
    $tasks_new = [];
    $cur_date = time();
    foreach ($tasks as $task) {
        $task_date = strtotime(getValue($task, 'due_date'));

        if ($task_date != 0) {
            $diff = floor(($cur_date - $task_date) / 3600);
            if ($diff < 24 && $diff > 0) {
                $tasks_new[] = $task;
            }
        }
    }
    return $tasks_new;
}

/**
 * функция, возвращающая массив задач на завтра
 * @param array $tasks массив задач
 *
 * @return array массив задач на завтра
 */
function getTaskTomorrow($tasks)
{
    $tasks_new = [];
    $cur_date = time();
    foreach ($tasks as $task) {
        $task_date = strtotime(getValue($task, 'due_date'));

        if ($task_date != 0) {
            $diff = floor(($task_date - $cur_date) / 3600);
            if ($diff < 24 && $diff > 0) {
                $tasks_new[] = $task;
            }
        }
    }
    return $tasks_new;
}

/**
 * функция, возвращающая массив просроченных задач
 * @param array $tasks массив задач
 *
 * @return array массив просроченных задач
 */
function getTaskOverdue($tasks)
{
    $tasks_new = [];
    $cur_date = time();
    foreach ($tasks as $task) {
        $task_date = strtotime(getValue($task, 'due_date'));
        if ((int)$task_date !== 0 && getValue($task, 'status') !== 1) {
            $diff = floor(($cur_date - $task_date) / 3600);
            if ($diff >= 24) {
                $tasks_new[] = $task;
            }
        }
    }
    return $tasks_new;
}

/*объявление переменных*/
$project_id = null;
if (isset($_SESSION['user'])) {
    $user_id = $_SESSION['user'];
    $projects = getProjects($con, $user_id);
    $tasksAll = array_reverse(getTasksAll($con, $user_id));
    $user_name = getUserName($con, $user_id);
    $tasks_filter = ['Все задачи', 'Повестка дня', 'Завтра', 'Просроченные'];
} else {
    header('Location: pages/guest.php');
}

/*проверка выбранного id проекта в адресной строке*/
if (isset($_SESSION['user']) && isset($_GET['project_id'])) {
    $project_id = (int)$_GET['project_id'];
    if (!isValueInArray($projects, 'id', $project_id)) {
        http_response_code(404);
        exit();
    }
}

/*формирование массива задач для конкретного пользователя и проекта*/
if (isset($_SESSION['user'])) {
    $tasks = array_reverse(getTasks($con, $user_id, $project_id));
}

/*проверка наличия запроса на инвертирование статуса задачи*/
if (isset($_GET['task_completed'])) {
    $task = getTaskWhereId($con, $_GET['task_completed']);
    changeStatus($con, $task);
    header('Location: ./index.php');
}

/*проверка выбора фильтра задач*/
if (isset($_GET['filter'])) {
    switch ($_GET['filter']) {
        case 1:
            $tasks = getTasksToday($tasks);
            break;
        case 2:
            $tasks = getTaskTomorrow($tasks);
            break;
        case 3:
            $tasks = getTaskOverdue($tasks);
            break;
        default:
            break;
    }
}

/*проверка отправки запроса поиска задач*/
if (isset($_GET['search'])) {
    $search = trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS));

    if (!empty($search)) {
        $tasks = getSearchTasks($con, $search, $user_id);
    }
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Дела в порядке</title>
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/flatpickr.min.css">
</head>

<body>
    <h1 class="visually-hidden">Дела в порядке</h1>

    <div class="page-wrapper">
        <div class="container container--with-sidebar">
            <?php if (isset($_SESSION['user'])): ?>
                <header class="main-header">
                    <a href="index.php">
                        <img src="img/logo.png" width="153" height="42" alt="Логотип Дела в порядке">
                    </a>

                    <div class="main-header__side">
                        <a class="main-header__side-item button button--plus open-modal" href="pages/form-task.php">Добавить
                            задачу</a>

                        <div class="main-header__side-item user-menu">
                            <div class="user-menu__data">
                                <p>
                                    <?= htmlspecialchars($user_name); ?>
                                </p>
                                <a href="php/logout.php">Выйти</a>
                            </div>
                        </div>
                    </div>
                </header>
            <?php else: ?>
                <header class="main-header">
                    <a href="/">
                        <img src="../img/logo.png" width="153" height="42" alt="Логитип Дела в порядке">
                    </a>

                    <div class="main-header__side">
                        <a class="main-header__side-item button button--transparent" href="../php/auth.php">Войти</a>
                    </div>
                </header>
            <?php endif; ?>

            <div class="content">
                <section class="content__side">
                    <h2 class="content__side-heading">Проекты</h2>

                    <nav class="main-navigation">
                        <ul class="main-navigation__list">
                            <?php foreach ($projects as $project): ?>
                                <li class="main-navigation__list-item <?php if (
                                    (int) getValue(
                                        $_GET,
                                        'project_id'
                                    ) === $project['id']
                                ): ?> main-navigation__list-item--active<?php endif; ?>">
                                    <a class="main-navigation__list-item-link"
                                        href="index.php?project_id=<?= $project['id'] ?>"><?= htmlspecialchars($project['name']); ?></a>
                                    <span class="main-navigation__list-item-count">
                                        <?= countProjectTasks(
                                            $tasksAll,
                                            $project['id']
                                        ); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>

                    <a class="button button--transparent button--plus content__side-button"
                        href="pages/form-project.php" target="project_add">Добавить проект</a>
                </section>

                <main class="content__main">
                    <h2 class="content__main-heading">Список задач</h2>

                    <form class="search-form" action="index.php" method="get" autocomplete="off">
                        <input class="search-form__input" type="text" name="search"
                            value="<?= trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS)) ?>"
                            placeholder="Поиск по задачам">

                        <input class="search-form__submit" type="submit" name="" value="Искать">
                    </form>

                    <div class="tasks-controls">
                        <nav class="tasks-switch">
                            <?php for ($index = 0; $index < count($tasks_filter); $index++): ?>
                                <a href="<?= getNewURL('filter', $index) ?>" class="tasks-switch__item <?php if (
                                       getValue(
                                           $_GET,
                                           'filter'
                                       ) == $index
                                   ): ?> tasks-switch__item--active<?php endif; ?>"><?= $tasks_filter[$index]; ?></a>
                            <?php endfor; ?>
                        </nav>
                        <label class="checkbox">
                        <input class="checkbox__input visually-hidden show_completed" type="checkbox"
                                <?php if (getValue($_GET, 'show_completed') === '1'): ?>checked <?php endif; ?>>
                            <span class="checkbox__text">Показывать выполненные</span>                    
                        </label>
                    </div>

                    <table class="tasks">
                        <?php foreach ($tasks as $task): ?>
                            <?php if (isset($task['status'])): ?>
                                <?php if (getValue($_GET, 'show_completed') == 0 && $task['status']):
                                    continue ?>
                                <?php endif; ?>
                                <tr
                                    class="tasks__item task<?php if ($task['status']): ?> task--completed<?php endif ?><?php if (isset($task['due_date']) && isDateDiffLess($task['due_date']) && $task['status'] != 1): ?> task--important<?php endif; ?>">
                                <?php endif; ?>
                                <td class="task__select">
                                    <label class="checkbox task__checkbox">
                                        <input class="checkbox__input visually-hidden task__checkbox" type="checkbox"
                                            onchange="changeTask(this.value)" value="<?= getValue($task, 'id'); ?>" <?php if (
                                                   getValue(
                                                       $task,
                                                       'status'
                                                   )
                                               ): ?> <?php endif; ?>>
                                        <span class="checkbox__text">
                                            <?= htmlspecialchars(getValue($task, 'name')); ?>
                                        </span>
                                    </label>
                                </td>

                                <td class="task__file">
                                    <?php if (isset($task['file_path'])): ?>
                                        <a class="download-link" href="<?= getUrl($task['file_path']); ?>"><?= htmlspecialchars(basename($task['file_path'])); ?></a>
                                    <?php endif; ?>
                                </td>

                                <td class="task__date">
                                    <?php if (isset($task['due_date'])):
                                        print(
                                            date(
                                                'd.m.Y',
                                                strtotime($task['due_date'])
                                            )
                                        );
                                    else:
                                        print("Нет"); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!empty($_GET['search']) && empty($tasks)): ?>
                            <p class="error-message">Ничего не найдено по вашему запросу</p>
                        <?php endif; ?>
                    </table>
                </main>
            </div>
        </div>
    </div>

    <footer class="main-footer">
        <div class="container">
            <div class="main-footer__copyright">
                <p>© 2019, «Дела в порядке»</p>

                <p>Веб-приложение для удобного ведения списка дел.</p>
            </div>

            <a class="main-footer__button button button--plus" href="pages/form-task.php">Добавить задачу</a>

            <div class="main-footer__social social">
                <span class="visually-hidden">Мы в соцсетях:</span>
                <a class="social__link social__link--facebook" href="#">
                    <span class="visually-hidden">Facebook</span>
                    <svg width="27" height="27" viewBox="0 0 27 27" xmlns="http://www.w3.org/2000/svg">
                        <circle stroke="#879296" fill="none" cx="13.5" cy="13.5" r="12.667" />
                        <path fill="#879296"
                            d="M14.26 20.983h-2.816v-6.626H10.04v-2.28h1.404v-1.364c0-1.862.79-2.922 3.04-2.922h1.87v2.28h-1.17c-.876 0-.972.322-.972.916v1.14h2.212l-.245 2.28h-1.92v6.625z" />
                    </svg>
                </a><span class="visually-hidden">
                    ,</span>
                <a class="social__link social__link--twitter" href="#">
                    <span class="visually-hidden">Twitter</span>
                    <svg width="27" height="27" viewBox="0 0 27 27" xmlns="http://www.w3.org/2000/svg">
                        <circle stroke="#879296" fill="none" cx="13.5" cy="13.5" r="12.687" />
                        <path fill="#879296"
                            d="M18.38 10.572c.525-.336.913-.848 1.092-1.445-.485.305-1.02.52-1.58.635-.458-.525-1.12-.827-1.816-.83-1.388.063-2.473 1.226-2.44 2.615-.002.2.02.4.06.596-2.017-.144-3.87-1.16-5.076-2.78-.22.403-.335.856-.332 1.315-.01.865.403 1.68 1.104 2.188-.397-.016-.782-.13-1.123-.333-.03 1.207.78 2.272 1.95 2.567-.21.06-.43.09-.653.088-.155.015-.313.015-.47 0 .3 1.045 1.238 1.777 2.324 1.815-.864.724-1.956 1.12-3.083 1.122-.198.013-.397.013-.595 0 1.12.767 2.447 1.18 3.805 1.182 4.57 0 7.066-3.992 7.066-7.456v-.34c.49-.375.912-.835 1.24-1.357-.465.218-.963.36-1.473.42z" />
                    </svg>
                </a><span class="visually-hidden">
                    ,</span>
                <a class="social__link social__link--instagram" href="#">
                    <span class="visually-hidden">Instagram</span>
                    <svg width="27" height="27" viewBox="0 0 27 27" xmlns="http://www.w3.org/2000/svg">
                        <circle stroke="#879296" fill="none" cx="13.5" cy="13.5" r="12.687" />
                        <path fill="#879296"
                            d="M13.5 8.3h2.567c.403.002.803.075 1.18.213.552.213.988.65 1.2 1.2.14.38.213.778.216 1.18v5.136c-.003.403-.076.803-.215 1.18-.213.552-.65.988-1.2 1.2-.378.14-.778.213-1.18.216h-5.135c-.403-.003-.802-.076-1.18-.215-.552-.214-.988-.65-1.2-1.2-.14-.38-.212-.78-.215-1.182V13.46v-2.566c.003-.403.076-.802.214-1.18.213-.552.65-.988 1.2-1.2.38-.14.778-.212 1.18-.215H13.5m0-1.143h-2.616c-.526.01-1.048.108-1.54.292-.853.33-1.527 1-1.856 1.854-.184.493-.283 1.014-.292 1.542v5.232c.01.526.108 1.048.292 1.54.33.853 1.003 1.527 1.855 1.856.493.184 1.015.283 1.54.293H16.117c.527-.01 1.048-.11 1.54-.293.854-.33 1.527-1.003 1.856-1.855.184-.493.283-1.015.293-1.54V13.46v-2.614c-.01-.528-.11-1.05-.293-1.542-.33-.853-1.002-1.525-1.855-1.855-.493-.185-1.014-.283-1.54-.293-.665.01-.89 0-2.617 0zm0 3.093c-2.51.007-4.07 2.73-2.808 4.898 1.26 2.17 4.398 2.16 5.645-.017.285-.495.434-1.058.433-1.63-.006-1.8-1.47-3.256-3.27-3.25zm0 5.378c-1.63-.007-2.64-1.777-1.82-3.185.823-1.41 2.86-1.4 3.67.017.18.316.276.675.278 1.04.006 1.177-.95 2.133-2.128 2.128zm4.118-5.524c0 .58-.626.94-1.127.65-.5-.29-.5-1.012 0-1.3.116-.067.245-.102.378-.102.418-.005.76.333.76.752z" />
                    </svg>
                </a>
                <span class="visually-hidden">,</span>
                <a class="social__link social__link--vkontakte" href="#">
                    <span class="visually-hidden">Вконтакте</span>
                    <svg width="27" height="27" viewBox="0 0 27 27" xmlns="http://www.w3.org/2000/svg">
                        <circle stroke="#879296" fill="none" cx="13.5" cy="13.5" r="12.666" />
                        <path fill="#879296"
                            d="M13.92 18.07c.142-.016.278-.074.39-.166.077-.107.118-.237.116-.37 0 0 0-1.13.516-1.296.517-.165 1.208 1.09 1.95 1.58.276.213.624.314.973.28h1.95s.973-.057.525-.837c-.38-.62-.865-1.17-1.432-1.626-1.208-1.1-1.043-.916.41-2.816.886-1.16 1.236-1.86 1.13-2.163-.108-.302-.76-.214-.76-.214h-2.164c-.092-.026-.19-.026-.282 0-.083.058-.15.135-.195.225-.224.57-.49 1.125-.8 1.656-.973 1.61-1.344 1.697-1.51 1.59-.37-.234-.272-.975-.272-1.433 0-1.56.243-2.202-.468-2.377-.32-.075-.647-.108-.974-.098-.604-.052-1.213.01-1.793.186-.243.116-.438.38-.32.4.245.018.474.13.642.31.152.303.225.638.214.975 0 0 .127 1.832-.302 2.056-.43.223-.692-.167-1.55-1.618-.29-.506-.547-1.03-.77-1.57-.038-.09-.098-.17-.174-.233-.1-.065-.214-.108-.332-.128H6.485s-.312 0-.42.137c-.106.135 0 .36 0 .36.87 2 2.022 3.868 3.42 5.543.923.996 2.21 1.573 3.567 1.598z" />
                    </svg>
                </a>
            </div>

            <div class="main-footer__developed-by">
                <span class="visually-hidden">Разработано:</span>

                <a href="https://htmlacademy.ru/intensive/php">
                    <img src="img/htmlacademy.svg" alt="HTML Academy" width="118" height="40">
                </a>
            </div>
        </div>
    </footer>

    <script src="flatpickr.js"></script>
    <script src="script.js"></script>
</body>

</html>