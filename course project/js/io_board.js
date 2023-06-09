$(function() {

    // При нажатии на заголовок доски открывается модуль обновления
    $(document).on("click", "#board_title h1", function () {
        $('#board-modal').modal('show');
    });

   // Открыть модальное окно редактирования при нажатии кнопки Add Board
    $(document).on("click", "#board_add", function () {
        $("#board_new_title_text").val('');
        $("#board_new_color").val('#000');
        $('#board-add-modal').modal('show');
    });

   // Процесс сохранения после нажатия кнопки Save на модуле для доски и обновления
    $(document).on("click", "#board-modal #save-btn", function () {
        var board_title_text = $('#board_title_text').val();
        var board_color = $('#board_color').val();
        var id = $('#board_title').attr("data-id");
        exePost("boards", "save", id, board_title_text, board_color).done(function(data) {
            var detail = $.parseJSON(data);
            $('#board_title h1').html(detail['title']);
            $('body').css('background-color', detail['board_color']);
            $("input#board_title_text").val(detail['title']);
            $('#board_title').attr("data-id", detail['id']);
            getBoardList(); // Получение списка досок
            $('#board-modal').modal('hide'); // Закрыть модуль
        }).fail(function(data) {
            alert("system Error");
        });
    });

    // Процесс сохранения после нажатия кнопки Сохранить в модуль доски и регистрации
    $(document).on("click", "#board-add-modal #save-btn", function () {
        var board_title_text = $('#board_new_title_text').val();
        var board_color = $('#board_new_color').val();
        exePost("boards", "save", "new", board_title_text, board_color).done(function(data) {
            var detail = $.parseJSON(data);
            $('#board_title h1').html(detail['title']);
            $('body').css('background-color', detail['board_color']);
            $("input#board_title_text").val(detail['title']);
            $('#board_title').attr("data-id", detail['id']);
            $("#panel_area").html(''); // Очистите панель, так как это новая доска
            getBoardList(); // Получение списка досок
            $('#board-add-modal').modal('hide'); // Закрыть модуль
        }).fail(function(data) {
            alert("system Error");
        });
    });


    // Подтверждение при нажатии кнопки удаления на модальном экране доски объявлений
    $(document).on("click", "#board-modal #delete-btn", function () {
        if (window.confirm('Удалите эту доску. Вы уверены?')) {
            var id = $('#board_title').attr("data-id");
            exePost("boards", "del", id, "", "", "", "").done(function () {
                // Доска была удалена, и первоначальное отображение должно быть выполнено заново
                exePost("boards", "first", "", "", "").done(function (data) {
                    var detail = $.parseJSON(data);
                    $('#board_title h1').html(detail['title']);
                    $('#board_title').attr('data-id', detail['id']);
                    $('body').css('background-color', detail['board_color'])
                    $("input#board_title_text").val(detail['title']);
                    $("input#board_color").val(detail['board_color']);

                    // Отобразите соответствующую панель на доске
                    getPanels(detail['id']);
                }).fail(function (data) {
                    alert("system Error");
                });
                getBoardList(); // Получение списка досок
                $('#board-modal').modal('hide'); // Закрыть модуль
            }).fail(function () {
                alert("system Error");
            });
        }
    });
});

