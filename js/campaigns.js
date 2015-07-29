chooseListCaller = null; // id объекта, откуда был вызван choose list
needClear = true;
unsortedIDs = [];
filterPendingIntervalId = null;

$.fn.initDateSwitcher = function() {
    $('.switch').bootstrapSwitch({
        onText: 'всегда',
        onColor: 'success',
        offText: 'в период',
        offColor: 'warning',
        handleWidth : 70,
        size : 'small'
    });
};

// выставляет нужные состояния полям в зависимости от выбранных чекеров
$.fn.switchAutoDateState = function() {
    if ($('input[name="autoDatesSwitcher"]').bootstrapSwitch('state')) {
        $('.auto-date-settings input[type="checkbox"]').attr('disabled', true).attr('readOnly', 1);
        $('.auto-date-settings input[type="text"]').attr('disabled', true).attr('readOnly', 1);
    } else {
        $('.auto-date-settings input[type="checkbox"]').removeAttr('disabled').removeAttr('readOnly');
        $('.auto-date-settings input[type="checkbox"]').each(function(){
            $().switchDateField($(this), $(this).parents('.pull-left').next().find('input[type="text"]')) ;
        });
    }
};

$.fn.switchDateField = function(checker, field) {
    if (checker.is(':checked')) {
        field.removeAttr('disabled');
        field.attr('readonly', 0);
        field.removeAttr('readonly');
    } else {
        field.attr('disabled', true);
        field.attr('readonly', 1);
    }
};

$('#edit').on('change', 'input[name="dateStartSwitcher"]', function() {
    $().switchDateField($(this), $('input[name="Campaign[auto_start_date]"]'));
});

$('#add').on('change', 'input[name="dateStartSwitcher"]', function() {
    $().switchDateField($(this), $('input[name="Campaign[auto_start_date]"]'));
});

$('#edit').on('change', 'input[name="dateStopSwitcher"]', function() {
    $().switchDateField($(this), $('input[name="Campaign[auto_stop_date]"]'));
});

$('#add').on('change', 'input[name="dateStopSwitcher"]', function() {
    $().switchDateField($(this), $('input[name="Campaign[auto_stop_date]"]'));
});

$.fn.updateGrid = function() {
    $('.filter-tools .btn').addClass('disabled');
    
    table = $('.campaigns tbody');
    table.addClass('ui-state-disabled');
    table.sortable();
    table.sortable( "option", "disabled", true );
    table.css({opacity : 0.6});
    $('.ui-sortable').css({cursor:'pointer'});
    
    $.fn.yiiGridView.update(
        "campaigns-grid", 
        {
            complete: function(jqXHR, status) {
                $("#campaigns-grid").removeClass("grid-view-loading");
                $.fn.enableSort();
                sortingStarted = false;
                $('.campaigns tbody').removeClass('ui-state-disabled');
                $('.filter-tools .btn').removeClass('disabled');
            }
        }
    );
};

$.fn.filterGrid = function(enabledOnly) {
    if ($('.filter-tools .btn').hasClass('disabled'))
        return;

    $('.campaigns tbody').addClass('ui-state-disabled');
    $('.campaigns tbody').sortable();
    $('.campaigns tbody').sortable( "option", "disabled", true );
    
    if (enabledOnly === undefined)
    {
        enabledOnly = 0;
    }
    
    $('.filter-tools .btn').addClass('disabled');
    $.fn.yiiGridView.update(
        "campaigns-grid", 
        {
            url: "/adv/campaigns?enabledOnly=" + enabledOnly,
            complete: function(jqXHR, status) {
                $("#campaigns-grid").removeClass("grid-view-loading");
                $.fn.enableSort();
                sortingStarted = false;
                
                $('.filter-tools a').toggleClass('hide');
                $('.campaigns tbody').removeClass('ui-state-disabled');
                $('.filter-tools .btn').removeClass('disabled');
            }
        }
    );
};


// сортировка
$.fn.enableSort = function() {
    
    $('.campaigns tbody').sortable({
        receive : function(e, ui) {
            sortingStarted = false;
        },
        start : function(e, ui) {
            beforeIndex = unsortedIDs.indexOf(ui.item.attr('id'));
        },
        update : function(e, ui) {
            table = $('.campaigns tbody');
              
            itemsIndexesArray = $(this).sortable("toArray", {
                key: 'id',
                attribute: 'sort_id'
              });
              
              itemsArray = $(this).sortable("toArray", {
                key: 'id',
                attribute: 'id'
              });
              
            afterIndex = itemsArray.indexOf(ui.item.attr('id'));

            // Disable sortability
            table.sortable();
            table.sortable( "option", "disabled", true );
            table.css({opacity : 0.6});
            $('.ui-sortable').css({cursor:'pointer'});
            
            $('.alert-error').hide();
            $("#campaigns-grid").addClass("grid-view-loading");

            $.post(
                '/adv/campaigns/sort',
                {
                    sortedIds: itemsArray,
                    sortedId: ui.item.attr('id'),
                    beforeIndex: beforeIndex,
                    afterIndex: afterIndex
                },
                'json'
            ).error(function() {
                    $(':not(.filter-preview) >.alert-error').show();
                    // rollback sorting
                    $('.items').sortable('cancel');
            }).always(function() {
                $.fn.updateGrid();
            });
        }
    });
    
    unsortedIDs = $('.campaigns tbody').sortable('toArray', {
        key: 'id',
        attribute: 'id'
    });
};

$.fn.listAvailable = function(type) {
    $("#add-btn").button('loading');
    $.post('/adv/campaigns/listAvailableElements',
            {
                provider_id : currentProviderId,
                type : type
            },
            function(data) {
                $('#choose-list .modal-body').html(data);
                $('#' + chooseListCaller).modal('hide');
                $('#choose-list').modal('show');
            }
        ).always(function() {
            $('#' + chooseListCaller + ' .adv-types a').button('reset');
            $('#' + chooseListCaller + '-btn').button('reset');
        });
};

$.fn.clearForm = function() {
    if (needClear) {
        $('#add-frm #Campaign_title, #add-frm #Campaign_description').val('');
        $('input.item-id').val('');
        $('.adv-element-preview').html('');
        $(".filter-form select").val('');
        $(".filter-form .chzn-select").trigger("liszt:updated");
        $('#common_filters').val('');
        
        $('.filter-form .action input[type="checkbox"]').bootstrapSwitch('state', true);

        $('#autoDatesSwitcher').attr('checked', true);
        $('#autoDatesSwitcher').bootstrapSwitch('state', true);
        $('.auto-date-settings input[type="checkbox"]').attr('checked', false).attr('readonly', 1).attr('disabled', true);
        $('.auto-date-settings input[type="text"]').val('');
    }
};

$('.filter-tools').on('click', '#hide-hidden', function() {
    $.fn.filterGrid(1);
});

$('.filter-tools').on('click', '#show-hidden', function() {
    $.fn.filterGrid(0);
});

$.fn.sendSaveRequest = function(formId) {
    $("#model-alert-error").html("").hide();
    $("#" + formId + "-btn").button("loading");
    
    if ( ! filterPreviewPending ) {
        clearInterval(filterPendingIntervalId);
        $.ajax({
            url: '/adv/campaigns/' + formId,
            type: 'POST', 
            dataType : 'json',
            data: $("#" + formId + " form").serialize(),
            success : function(data) {
                if (data.success === true) {
                    $("#" + formId + "").modal("hide");
                    $.fn.yiiGridView.update("campaigns-grid");
                    $.fn.clearForm();
                } else {
                    $("#model-alert-error").html(data.error).show();
                }
            },
            complete : function() {
                $("#" + formId + "-btn").button("reset");
            }
        });
    }
};

// Редактирование
$(document).on('click', '.edit-company', function() {
    $("#campaigns-grid").addClass("grid-view-loading");
    $("#campaigns-grid .button-column a").addClass("disabled");
    $("#edit .modal-body").html("");
    $("#edit-btn").button("loading");
    $.get($(this).attr("href"), 
        function(data) {
            $("#edit .modal-body").html(data);
            $("#edit-btn").button("reset");

            $('body,html').animate({
                scrollTop: 0
            }, 400);

            $().initDatepicker();
            $().initFilterSwitcher();
            $().initDateSwitcher();
            $("#edit").modal("show");
            $().switchAutoDateState();
            
            $('#edit #autoDatesSwitcher').on('switchChange.bootstrapSwitch', function(event, state) {
                $().switchAutoDateState();
            });
    
            $("#campaigns-grid").removeClass("grid-view-loading");
        }
    );
    return false;
});

$('#edit').on('hidden', function () {
    $(".chzn-select").chosen('destroy');
});

$('#edit').on('shown', function () {
    $.fn.chznUpdate();
});

$('#add').on('shown', function () {
    $().switchAutoDateState();
});

// Возобновление показов
$(document).on('click', 'a.state[rel="disabled"]', function() {
    if ($(this).hasClass('disabled'))
        return false;
    
    if (confirm("Вы уверены, что хотите возобновить показы всех элементов кампании?")) {
        $(this).parents('td').find('.state').removeClass('open');
        $(this).parents('td').find('.grid-view-loading').show();
        $("#campaigns-grid").addClass("grid-view-loading");
        $("#campaigns-grid .button-column a").addClass("disabled");
        
        table = $('.campaigns tbody');
        table.addClass('ui-state-disabled');
        table.sortable();
        table.sortable( "option", "disabled", true );
        table.css({opacity : 0.6});
    
        $.post($(this).attr("href"),
            function(data) {
                $.fn.updateGrid();
            }
        );
    }

    return false;
});

$(document).on('click', 'a.state[rel="enabled"]', function(event) {
    if (confirm("Вы уверены, что хотите остановить показы всех элементов кампании?")) {
        $(this).parents('td').find('.state').removeClass('open');
        $(this).parents('td').find('.grid-view-loading').show();
        $("#campaigns-grid").addClass("grid-view-loading");
        $("#campaigns-grid .button-column a").addClass("disabled").attr("disabled", true);
        
        table = $('.campaigns tbody');
        table.addClass('ui-state-disabled');
        table.sortable();
        table.sortable( "option", "disabled", true );
        table.css({opacity : 0.6});
        
        $.post($(this).attr("href"),
            function(data) {
                $.fn.updateGrid();
            }
        );
    }

    return false;
});

// Удаление
$(document).on('click', '.campaigns a[rel="delete"]', function() {
	if(!confirm('Вы уверены, что хотите архивировать данный элемент?')) return false;
    
    $(this).parents('td').find('.state').removeClass('open');
    $(this).parents('td').find('.grid-view-loading').show();
    
    $.post($(this).attr('href'), function(data) {
        $.fn.updateGrid();
    });
    
	return false;
});
    
$(document).ready(function() {
    
    $.fn.enableSort();
    $().initDatepicker();
    $().initDateSwitcher();
    $().initFilterSwitcher();
    
    $('#add #autoDatesSwitcher').on('switchChange.bootstrapSwitch', function(event, state) {
        $().switchAutoDateState();
    });
    
    // показ окна выбора привязываемого элемента
    $('.modal-body').on('click', '.adv-types li > a[href="#choose"]', function() {
        chooseListCaller = $(this).parents('.modal').attr('id');
        $.fn.listAvailable($(this).attr('rel'));
        $(this).button('loading');
    });
    
    // отвязка элемента
    $('.modal-body').on('click', '.adv-types li > a[href="#detach"]', function() {
        if (confirm('Вы действительно хотите отвязать данный элемент от кампании?')) {
            advType = $(this).attr('rel');
            $('input[rel="' + advType + '"]').val("");
            $('.adv-element-preview.' + advType).html("").addClass('hide');
            $(this).addClass('hide');
        }
        
        return false;
    });
    
    $('#choose-list').on('hide', function() {
        needClear = false;
        $('#' + chooseListCaller).modal('show');
        needClear = true;
    });
    
    // Выбор привязываемого элемента
    $('#choose-list').on('click', '.adv_available_elements a', function() {
        advType = $(this).parents('.list-view').attr('rel');
        advId = $(this).parent('li').attr('id');
        $('input[rel="' + advType + '"]').val(advId);
        $.post('/adv/campaigns/viewAttachedElements', 
        {
            id : advId,
            type: advType,
            object_type : $(this).parent('li').attr('rel')
        },
        function(data) {
            $('.adv-element-preview.' + advType).html(data).removeClass('hide');
            $('#detach-adv-type-' + advType).removeClass('hide');
        });
        
        $('#choose-list').modal('hide');
    });
    
    $('.btn[data-target="#add"]').click(function() {
        $.fn.clearForm();
    });
    
    $('#edit').on('hide', function() {
        $("#campaigns-grid .button-column a").removeClass("disabled");
    });
    
    // открываем модальное окно игры по прямой ссылке
    campaignId = window.location.hash.match('#edit-(.*)');
    if (campaignId !== null) {
        $('.edit-company#' + campaignId[1]).click();
    }
    
    $('#campaigns-grid').on('mouseenter', '.game-error', function() {
        $(this).tooltip('show');
    });
    
    $('#campaigns-grid').on('mouseleave', '.game-error', function() {
        $(this).tooltip('hide');
    });
    
    $('#edit').on('click', '#edit-btn', function() {
        $("#edit-btn").button("loading");
        filterPendingIntervalId = setInterval(function() {$.fn.sendSaveRequest('edit');}, 1000);
    });
    
    $('#add').on('click', '#add-btn', function() {
        $("#add-btn").button("loading");
        filterPendingIntervalId = setInterval(function() {$.fn.sendSaveRequest('add');}, 1000);
    });
});