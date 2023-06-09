$(function() {

    // Нажмите на карточку, чтобы открыть модуль редактирования
    $(document).on('click', '.panel-body .card', function() {
        $("#card_title_text").val($(this).html());
        $('#card-edit').attr('data-id', $(this).attr("cardId"));
        $('#card-edit').attr('card_panel_id', $(this).parent().parent().find('.panel-heading h2').attr("data-id"));
        $("#card_label_color").val($(this).attr("label_color"));
        // Обновление ID дублирующего/перемещающего модуля
        $("#card-move-modal").attr('data-id', $(this).attr("cardId"));

        if ($(this).attr("cardId") === 'new') {
            $("#card_title_text").val('');
            $("#contents").val('');
            $("#contents_view").html('');
            $("#card_label_color").val("#cccccc");
            $("#card_label_color").spectrum({
                showSelectionPalette: true,
                preferredFormat: "hex",
                showInput: true,
                showInitial: true,
                showPaletteOnly: true, // Создание палитры только для внешнего вида
                palette: [// Цвета, которые будут использоваться в палитре
                    ["#ffffff", "#cccccc", "#999999", "#666666", "#333333", "#000000"],
                    ["#f44336", "#ff9800", "#ffeb3b", "#8bc34a", "#4caf50", "#03a9f4", "#2196f3"]
                ]
            });
        } else {
            // Получение и отображение содержимого
            exePost("cards", "find", $(this).attr("cardId"), "", "", "", "").done(function (data) {
                if (data) {
                    var detail = $.parseJSON(data);
                    $("#contents").val(detail['contents']);
                    $("#contents_view").html(nl2br(detail['contents'])).linkify({
                        target: "_blank"// Замените URL-адреса в карточках ссылками
                    });

                    $("#card_label_color").val(detail['label_color']);
                    $("#card_label_color").spectrum({
                        showSelectionPalette: true,
                        preferredFormat: "hex",
                        showInput: true,
                        showInitial: true,
                        showPaletteOnly: true, // Создание палитры только для внешнего вида
                        palette: [// Цвета, которые будут использоваться в палитре
                            ["#ffffff", "#cccccc", "#999999", "#666666", "#333333", "#000000"],
                            ["#f44336", "#ff9800", "#ffeb3b", "#8bc34a", "#4caf50", "#03a9f4", "#2196f3"]
                        ]
                    });

                }
            }).fail(function (data) {
                alert("system Error");
            });

            // Первоначальный вид, отключение режима редактирования
            $("#contents").css('display', 'none');
            $("#contents_view").css('display', 'block');
        }
        $('#card-edit').modal('show');
    });

    //После нажатия кнопки "Редактировать" на карточке переключите вид содержимого.
    $(document).on('click', '#contents_toggle', function () {
        $("#contents_view").toggle();
        $("#contents").toggle();
        if($("#contents_toggle").html() === "編集"){
            $("#contents_toggle").html("表示");
        }else{
            $("#contents_toggle").html("編集");
        }
    });


   // Другие карточки
    $(document).on('click', '.pannel .panel-footer .card_add', function () {
        $(this).parent().parent().children('.panel .panel-body').append($('<div class="card panel panel-default" label_color="" cardid="new">New</div>'));
    });

    // Процесс сохранения после нажатия кнопки сохранения на модуле карточки.
    $(document).on("click", "#card-edit .modal-dialog .modal-footer button#save-btn", function () {
        var card_title_text = $('#card_title_text').val();
        var card_label_color = $("#card_label_color").val();
        var contents = $("#contents").val();
        var card_id = $('#card-edit').attr('data-id');
        var board_id = $('#board_title').attr("data-id");
        var panel_id = $('#card-edit').attr('card_panel_id');

        // Новыq ID, если идентификатор не может быть получен
        if ( (typeof card_id === "undefined") || ( card_id === "") ) {
            card_id = 'new';
        }
        exePost("cards", "save", card_id, card_title_text, panel_id, card_label_color, contents).done(function(data) {
            var detail = $.parseJSON(data);
            var label_color = detail["label_color"];
            if(label_color == null){
                label_color = "";
            }
            // Если в той же панели есть хотя бы один card_id = new, обновите первый элемент.
            // Если нет new, должен быть id, поэтому найдите и обновите элемент на основе id.
            // Обновите заголовок и метку с сохраненными данными.
            if( $("#panel_area .panel h2[data-id='"+panel_id+"']").parent().parent().find(".panel-body .card[cardId='new']").length > 0) {
                $("#panel_area .panel h2[data-id='"+panel_id+"']").parent().parent().find(".panel-body .card[cardId='new']:last-child").html(detail["title"]);
                $("#panel_area .panel h2[data-id='"+panel_id+"']").parent().parent().find(".panel-body .card[cardId='new']:last-child").attr("style", "border-top: 12px solid "+label_color);
                $("#panel_area .panel h2[data-id='"+panel_id+"']").parent().parent().find(".panel-body .card[cardId='new']:last-child").attr("cardID", detail["id"]);
            }else{
                $(".card[cardId='"+detail["id"]+"']").html(detail["title"]);
                $(".card[cardId='"+detail["id"]+"']").attr("style", "border-top: 12px solid "+label_color);
            }

            // Сброс внутри модуля, а затем закрытие модуля.
            $('#card_title_text').val('');
            $("#card_label_color").val('');
            $("#contents").val('');
            $('#card-edit').modal('hide');
        }).fail(function(data) {
            alert("system Error");
        });
    });

    // Подтверждение удаления отображается при нажатии кнопки Delete в модуле карточки
    $(document).on("click", "#card-edit .modal-dialog .modal-footer button#delete-btn", function () {
        if (window.confirm('このカードを削除します。よろしいですか？')) {
            var card_id = $('#card-edit').attr('data-id');
            var panel_id = $('#card-edit').attr('card_panel_id');
            exePost("cards", "del", card_id, "", "", "", "").done(function () {
                $("#panel_area .panel h2[data-id='" + panel_id + "']").parent().parent().find(".panel-body .card[cardId='" + card_id + "']").remove();
                $('#card-edit').modal('hide');
            }).fail(function () {
                alert("system Error");
            });
        }
    });


    // Нажатие кнопки перемещения/дублирования на модуле карточки открывает специальный модуль.
    $(document).on("click", "#card-edit .modal-dialog .modal-footer button#move-btn", function () {
        $('#card-move-modal').modal('show');
        // Получение списка досок и отражение его в модуле
        $("#board_select").html('');
        $("#panel_select").html('');
        exePost("boards", "list", "", "", "").done(function(data) {
            var obj = $.parseJSON(data);
            var lists = "<option value=''>-</option>";
            $.each(obj, function(index, value) {
                lists += "<option value='" + value["id"]+"'>" + value["title"] + "</option>";
            });
            $("#board_select").append(lists);
        }).fail(function(data) {
            alert("system Error");
        });
    // После выбора списка досок обновить список панелей
        $(document).on("change", "#board_select", function () {
            exePost("panels", "list", $(this).val(), "", "").done(function(data) {
                if(data !==false){
                    var obj = $.parseJSON(data);
                    var lists = "";
                    $.each(obj, function(index, value) {
                        lists += "<option value='" + value["id"]+"'>" + value["title"] + "</option>";
                    });
                }
                $("#panel_select").html('');
                $("#panel_select").append(lists);
            }).fail(function(data) {
                alert("system Error");
            });
        });

       // Когда нажата кнопка "Выполнить", значения формы извлекаются и обрабатываются
        $(document).on("click", "#card-move-modal .modal-dialog .modal-footer button#save-panel-btn", function () {
            var boards_id = $("#board_select").val();
            var panels_id = $("#panel_select").val();
            var id = $("#card-move-modal").attr('data-id');
            var mode = $("input[name='q']:radio:checked").val();
            exePost("cards", mode, id, panels_id, "", "", "").done(function () {
                $("#panel_area").html('');
                /// Совет извлекает наименьший id с флагом удаления 0 и извлекает заголовок и цвет фона
                exePost("boards", "first", "", "", "").done(function (data) {
                    if (data) {
                        var detail = $.parseJSON(data);
                        $('#board_title h1').html(detail['title']);
                        $('#board_title').attr('data-id', detail['id']);
                        $('body').css('background-color', detail['board_color'])
                        $("input#board_title_text").val(detail['title']);
                        $("input#board_color").val(detail['board_color']);
                        $("input#board_color").spectrum({
                            showSelectionPalette: true,
                            preferredFormat: "hex",
                            showInput: true,
                            showInitial: true,
                            showPaletteOnly: true, // Сделайте внешний вид только для палитры.
                            palette: [// Цвета, которые будут использоваться в палитре
                                ["#ffffff", "#cccccc", "#999999", "#666666", "#333333", "#000000"],
                                ["#f44336", "#ff9800", "#ffeb3b", "#8bc34a", "#4caf50", "#03a9f4", "#2196f3"]
                            ]
                        });
                        // Отобразите соответствующую панель на доске.
                        getPanels(detail['id']);
                        $('#card-move-modal').modal('hide');
                    }
                }).fail(function (data) {
                    alert("system Error");
                });

            }).fail(function () {
                alert("system Error");
            });
        });
    });
});
