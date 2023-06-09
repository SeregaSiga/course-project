$(function() {
    // Другие панели
    $("#panel_add").on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        // Получение количества панелей при их добавлении
        var count = $(".pannel").length;
        if (count > 7) {
            alert('Вы можете добавить только до семи панелей');
            return;
        }
        // // Добавить пустую панель
        $("#panel_area").append($("#hidden .panel").html());

        // Изменена конфигурация, чтобы карточки на панели можно было перетаскивать
        $("#panel_area .panel-body").sortable({
            connectWith: '.panel-body'
        });
        return false;
    });

    // Щелкните по заголовку на панели, чтобы открыть модуль редактирования
    $(document).on("click", ".panel h2", function () {
        var id = $(this).attr("data-id");
        $("#panel-modal").attr("data-id", id);
        if(id ==='new'){
            $('#panel-modal #panel_title_text').val('');
        }else{
            $('#panel-modal #panel_title_text').val($(this).html());
        }

        $('#panel-modal').modal('show');
    });

    // Процесс сохранения после нажатия кнопки сохранения на модуле панели.
    $(document).on("click", "#panel-modal .modal-dialog .modal-footer button#save-panel-btn", function () {
        var panel_title_text = $('#panel_title_text').val();
        var panel_id = $('#panel-modal').attr('data-id');
        var board_id = $('#board_title').attr("data-id");
        // Новый ID, если идентификатор не может быть получен
        if ( (typeof panel_id === "undefined") || ( panel_id === "")) {
            panel_id = 'new';
        }
        exePost("panels", "save", panel_id, panel_title_text, board_id).done(function(data) {
            var detail = $.parseJSON(data);
            // Обновление заголовка с сохраненными данными.
            // Если данные новые, целевой data-id = new, иначе поиск по существующему ID
            if(panel_id === 'new'){
                $("#panel_area .panel").find("h2[data-id='new']").eq(0).html(detail['title']);
                $("#panel_area .panel").find("h2[data-id='new']").eq(0).attr("data-id", detail['id']);
            }else{
                $("#panel_area .panel h2[data-id='"+panel_id+"']").attr("data-id", detail['id']);
                $("#panel_area .panel h2[data-id='"+panel_id+"']").html(detail['title']);
            }
            $('#panel-modal').modal('hide'); // Закрыть модуль
        }).fail(function(data) {
            alert("system Error");
        });
    });

    // Подтверждение при нажатии кнопки удаления на модуле панели
    $(document).on("click", "#panel-modal .modal-dialog .modal-footer button#delete-btn", function () {
         if(window.confirm('Удалите эту панель. Вы уверены?')){
		    var panel_id = $('#panel-modal').attr('data-id');
		    exePost("panels", "del", panel_id, "", "", "", "").done(function() {
		        $("#panel_area .panel h2[data-id='"+panel_id+"']").parent().parent().remove();
		        $('#panel-modal').modal('hide'); // Закрыть модуль
		    }).fail(function() {
                alert("system Error");
            });
	    }
    });

});

