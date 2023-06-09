<?php
$ru = [
    'LOGIN_COOKIE_MSG' => 'Сохраните информацию для входа в систему',
    'LOGIN_BUTTON'     => 'Вход',
    'BOARD'            => 'Доска',
    'ADD_BOARD'        => 'Добавить доску',
    'ADD_PANEL'        => 'Добавить панель',
    'LOGOUT'           => 'Выход из системы',
    'ENTER_KEYWORD'    => 'Пожалуйста, введите',
    'BOARD_EDIT'       => 'Редактирование доски',
    'BOARD_NAME'       => 'Название',
    'BOARD_COLOR'      => 'Цвет',
    'BOARD_DELETE'     => 'Удалить',
    'BOARD_CLOSE'      => 'Закрыть',
    'BOARD_SAVE'       => 'Сохранить',
    'BOARD_ADD'        => 'Дополнение к доске',
    'EDIT_PANNEL'      => 'Редактирование панели',
    'PANEL_NAME'       => 'Название',
    'PANEL_DELETE'     => 'Удалить',
    'PANEL_CLOSE'      => 'Закрыть',
    'PANEL_SAVE'       => 'Сохранить',
    'ADD_CARD'         => 'Добавить карточку',
    'EDIT_CARD'        => 'Редактирование информации о карточке',
    'CARD_TITLE'       => 'Название',
    'COLOR_LABEL'      => 'Цвет',
    'CARD_CONTENTS'    => 'Содержимое',
    'CARD_EDIT'        => 'Редактировать',
    'CARD_VIEW'        => 'Скрыть',
    'CARD_DELETE'      => 'Удалить',
    'CARD_MOVE'        => 'Перемещение / копирование',
    'CARD_CLOSE'       => 'Закрыть',
    'CARD_SAVE'        => 'Сохранить',
    'CARD_MOVE_TITLE'  => 'Перемещение / копирование карточки',
    'CARD_MOVE_BOARD'  => 'Доски',
    'CARD_MOVE_PANNEL' => 'Панели',
    'CARD_MOVE_MOVE'   => 'Переместить',
    'CARD_MOVE_COPY'   => 'Копировать',
    'CARD_MOVE_CLOSE'  => 'Закрыть',
    'CARD_MOVE_EXE'    => 'Выполнить',
    'TIMEOUT_TITLE'    => 'Время ожидания',
    'TIMEOUT_MSG'      => 'Обработка занимает время.<br> Пожалуйста, повторите попытку через некоторое время.',
    'SEARCH_RESULT'    => 'Результаты поиска',
    'SEARCH_COMMENT'   => 'Отображается до 100 элементов',
    'DELETE_PANEL_MSG' => 'Удалить эту панель. Ок?',
    'DELETE_CARD_MSG'  => 'Удалить эту карточку. Ок?',
    'DELETE_BOARD_MSG' => 'Удалить эту доску. Ок？'
];

$en = [
    'LOGIN_COOKIE_MSG' => 'Record login information',
    'LOGIN_BUTTON'     => 'Login',
    'BOARD'            => 'Board',
    'ADD_BOARD'        => 'Add Board',
    'ADD_PANEL'        => 'Add Panel',
    'LOGOUT'           => 'Logout',
    'ENTER_KEYWORD'    => 'Please input',
    'BOARD_EDIT'       => 'Board editing',
    'BOARD_NAME'       => 'Name',
    'BOARD_COLOR'      => 'Color',
    'BOARD_DELETE'     => 'Delete',
    'BOARD_CLOSE'      => 'Close',
    'BOARD_SAVE'       => 'Save',
    'BOARD_ADD'        => 'Board addition',
    'EDIT_PANNEL'      => 'Panel editing',
    'PANEL_NAME'       => 'Title',
    'PANEL_DELETE'     => 'Delete',
    'PANEL_CLOSE'      => 'Close',
    'PANEL_SAVE'       => 'Save',
    'ADD_CARD'         => 'Add Card',
    'EDIT_CARD'        => 'Card information editing',
    'CARD_TITLE'       => 'Title',
    'COLOR_LABEL'      => 'Label',
    'CARD_CONTENTS'    => 'Contents',
    'CARD_EDIT'        => 'Edit',
    'CARD_VIEW'        => 'View',
    'CARD_DELETE'      => 'Delete',
    'CARD_MOVE'        => 'Move / copy',
    'CARD_CLOSE'       => 'Close',
    'CARD_SAVE'        => 'Save',
    'CARD_MOVE_TITLE'  => 'Move / copy card',
    'CARD_MOVE_BOARD'  => 'Boards',
    'CARD_MOVE_PANNEL' => 'Panels',
    'CARD_MOVE_MOVE'   => 'Move',
    'CARD_MOVE_COPY'   => 'Copy',
    'CARD_MOVE_CLOSE'  => 'Close',
    'CARD_MOVE_EXE'    => 'Exe',
    'TIMEOUT_TITLE'    => 'TimeOut',
    'TIMEOUT_MSG'      => 'Processing takes time.<br>Please try again after a while.',
    'SEARCH_RESULT'    => 'Search Results',
    'SEARCH_COMMENT'   => 'Up to 100 items will be displayed',
    'DELETE_PANEL_MSG' => 'Delete this panel. Is it OK?',
    'DELETE_CARD_MSG'  => 'Delete this card. Is it OK?',
    'DELETE_BOARD_MSG' => 'Delete this board. Is it OK?'
];

foreach ((array)${LANG} as $key => $val) {
    define($key, $val);
}
