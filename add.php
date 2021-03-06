<?php
$categories = [];

require_once('helpers.php');
require_once('functions.php');
$user_id = '';

/* проверяем сессию, анонимного пользователя переадресуем на вход */
if(session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (isset($_SESSION['user'])) {
$user = $_SESSION['user'];
$user_id = $user['id'];
} else {
    header('Location: index.php');
}

/* подключаемся к базе данных */
if (!isset($con)) {
    $con = require_once('init.php');
}

if ($con) {
    mysqli_set_charset($con, 'utf8');

/* получаем список категорий */
    $sql_categories = "SELECT title, id FROM projects WHERE user_id = $user_id";
    $result = mysqli_query($con, $sql_categories);
    $categories = mysqli_fetch_all($result, MYSQLI_ASSOC);

/* создаем форму */
    $content = include_template('add.php', ['categories' => $categories]);

/* при попытке отправки формы */
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
/* валидация полученных данных */
        $required = ['name', 'project'];
        $errors = [];

        $rules = [
            'date' => function($value) {
                return checking_date($value);
            },
            'project' => function($value) use ($categories) {
                return checking_project($value, $categories);
            }
        ];

        $task = filter_input_array(INPUT_POST, ['name' => FILTER_DEFAULT, 'project' => FILTER_DEFAULT, 'date' => FILTER_DEFAULT], true);

        foreach ($task as $key => $value)  {
            if (isset($rules[$key])) {
                $rule = $rules[$key];
                $errors[$key] = $rule($value);
            }
            if (empty($value)) {
                if (in_array($key, $required)) {
                    $errors[$key] = 'Это поле должно быть заполнено';
                }
                $task[$key] = NULL;
            }
        }

        $errors = array_filter($errors);

        if (!count($errors)) {
/* при отсутствии ошибок в других полях работаем с файлом */
            $task['file_name'] = NULL;
            $task['path'] = NULL;
            if ($_FILES['file']['name']) {
                $task['file_name'] = htmlspecialchars($_FILES['file']['name']);
                $task['path'] = 'uploads/' . uniqid() . strrchr($task['file_name'], ".");
                move_uploaded_file($_FILES['file']['tmp_name'], $task['path']);
            }
/* сохраняем задачу */
            $task['user'] = $user_id;
            $sql_task_insert = 'INSERT INTO tasks (title, project_id, deadline, file_name, file_url, user_id) VALUES (?, ?, ?, ?, ?, ?)';
            $stmt = db_get_prepare_stmt($con, $sql_task_insert, $task);
            $result = mysqli_stmt_execute($stmt);
/* переадресация при успехе */
            if ($result) {
                header('Location: index.php');
            }
        } else {
            $content = include_template('add.php', ['task' => $task, 'errors' => $errors, 'categories' => $categories]);
        }
    }

    print(include_template('../index.php', ['con' => $con,'content' => $content]));

}
?>
