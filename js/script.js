const apiClient = {
  //data is JSON object
  async get(action, data = {}) {
    return $.ajax({
      url: `api.php?action=${action}`,
      method: "GET",
      data,
    });
  },

  async post(action, data = {}) {
    return $.ajax({
      url: `api.php?action=${action}`,
      method: "POST",
      data,
    });
  },
};
async function getAuth() {
  try {
    const response = await apiClient.get("getAuth");
    if (!response.success && response.message == "NOT_AUTHENTICATED") {
      window.location.href = response.relogin;
      return "NOT_AUTHENTICATED";
    } else {
      $("#loadingScreen").hide();
      return response.message;
    }
  } catch (error) {
    showFlashMessage("Something went wrong!", "danger");
    console.error("Error on getAuth API:", error);
  }
}
async function handleTaskClick(task) {
  const authMessage = await getAuth();
  if (authMessage == "NOT_AUTHENTICATED") {
    return;
  } else if (authMessage == "EDIT_ACCESS_FALSE") {
    $("#editBtn").hide();
    $("#delete-btn").hide();
  }
  try {
    const response = await apiClient.post("getSolo", { id: task.id });
    $("#class-name").html(response.name);
    $("#status").html(`<strong>Status:</strong> ${response.status}`);
    $("#task-trainer").html(`<strong>Trainer:</strong> ${response.trainer}`);
    $("#task-brand").html(`<strong>Brand:</strong> ${response.brand}`);
    $("#task-type").html(`<strong>Type:</strong> ${response.type}`);
    $("#start-date").html(
      `<strong>Start date:</strong> ${response.formatted_start}`
    );
    $("#end-date").html(`<strong>End date:</strong> ${response.formatted_end}`);
    $("#notes").html(response.notes);

    $(".task-panel").addClass("active");

    $("#edit-ClassId").val(response.id);
    $("#edit-ClassName").val(response.name);
    $("#edit-status").val(response.status);
    $("#edit-classFacilitator").val(response.trainerEID);
    $("#edit-brand").val(response.brand);
    $("#edit-type").val(response.type);
    $("#edit-start-date").val(response.start.slice(0, 10));
    $("#edit-end-date").val(response.end.slice(0, 10));
    $("#edit-notes").val(response.notes);

    if (response.type == "Module") {
      $("#task-trainer").hide();
      $(".div-edit-facilitator").hide();
      // $("#classFacilitator").removeAttr("required");
    } else {
      $("#task-trainer").show();
      $(".div-edit-facilitator").show();

      //$("#classFacilitator").prop("required", true);
    }

    $("#delete-btn").attr("data-delete-class-id", response.id);
    const $parent = $(`[data-task-id='${task.id}']`);
    $parent.find(".task-start").text(response.formatted_start);
  } catch (error) {
    showFlashMessage("Something went wrong!", "danger");
    console.error("Error on getSolo API:", error);
  }
}

async function setupGanttApp(authMessage) {
  const ganttTarget = "#gantt-target";
  let ganttInstance = null;

  const debounce = (fn, delay) => {
    let timeout;
    return (...args) => {
      clearTimeout(timeout);
      timeout = setTimeout(() => fn.apply(this, args), delay);
    };
  };

  async function loadTrainers() {
    try {
      const trainers = await apiClient.get("getTrainers");

      const controlselect = $("#controlFacilitator");
      trainers.forEach((trainer) => {
        controlselect.append(
          `<option value="${trainer.vd_employeeid}">${trainer.vd_firstname}</option>`
        );
      });
      $("#controlFacilitator").selectpicker("refresh");

      const controlFacilitator = Cookies.get("controlFacilitator");
      if (controlFacilitator) {
        let saved = [];
        try {
          saved = JSON.parse(controlFacilitator);
        } catch {
          saved = String(controlFacilitator).split(",");
        }
        $("#controlFacilitator").selectpicker("val", saved);
      }

      const select = $('select[name="classFacilitator"]');
      trainers.forEach((trainer) => {
        select.append(
          `<option value="${trainer.vd_employeeid}">${trainer.vd_firstname}</option>`
        );
      });
    } catch (error) {
      $("#trainer").html("<option>Error loading trainers</option>");
      console.error("Error loading trainers:", error);
    }
  }
  window.loadTrainers = loadTrainers;

  async function loadTasks() {
    try {
      const data = await apiClient.get("read");
      //alert(data.message);
      //return;
      const tasks = data.map((task) => ({
        id: task.id,
        name: task.name,
        trainer: task.trainer,
        type: task.type,
        status: task.status,
        custom_class: task.custom_class,
        start: new Date(task.start).toISOString(),
        end: new Date(task.end).toISOString(),
        au_start: task.start,
        au_end: task.end,
        formatted_start: task.formatted_start,
        formatted_end: task.formatted_end,
      }));

      const $tableBody = $("#taskTableBody");
      $tableBody.empty();
      tasks.forEach((t) => {
        const $row = $(`
        <div class="table-row" data-task-id="${t.id}">
          <div class="task-name">${t.name}</div>
          <div class="task-assignee">${t.trainer}</div>
          <div class="task-type">${t.type}</div>
          <div class="task-status">${t.status}</div>
          <div class="task-start">${t.formatted_start}</div>
        </div>
      `);
        $tableBody.append($row);
      });

      const $Lastrow = $(`
        <div style="height: 48px;" >
          
        </div>
      `);

      $tableBody.append($Lastrow);

      if (!ganttInstance) {
        ganttInstance = new Gantt(ganttTarget, tasks, {
          popup: false,
          view_mode: "Day",
          move_dependencies: true,
          date_format: "YYYY-MM-DD",
          on_date_change: debounce(handleDateChange, 1000),
          // on_click: handleTaskClick,
        });
      } else {
        ganttInstance.refresh(tasks);
      }
      if (authMessage == "EDIT_ACCESS_FALSE") {
        $(".bar-wrapper, .handle").css("pointer-events", "none");
      }
    } catch (error) {
      console.error("Error loading tasks:", error);
    }
  }

  function showFlashMessage(message, type = "success") {
    const alert = $(`
    <div class="flash-message alert alert-${type} alert-dismissible fade show" role="alert">
      ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  `);

    $("#flash-container").append(alert);
    alert.fadeIn(300);

    setTimeout(() => {
      alert.fadeOut(500, function () {
        $(this).remove();
      });
    }, 5000);
  }
  window.showFlashMessage = showFlashMessage;
  async function handleDateChange(task, start, end) {
    const authMessage = await getAuth();
    if (authMessage == "NOT_AUTHENTICATED") {
      return;
    } else if (authMessage == "EDIT_ACCESS_FALSE") {
      return;
    }
    try {
      const response = await apiClient.post("update", {
        id: task.id,
        start: start,
        end: end,
      });

      response.id = response.id.toString();

      ganttInstance.update_task(response.id, response);

      $("#start-date").html(
        `<strong>Start date:</strong> ${response.formatted_start}`
      );
      $("#end-date").html(
        `<strong>End date:</strong> ${response.formatted_end}`
      );

      $("#edit-start-date").val(response.start.slice(0, 10));
      $("#edit-end-date").val(response.end.slice(0, 10));

      const $parent = $(`[data-task-id='${response.id}']`);
      $parent.find(".task-start").text(response.formatted_start);

      showFlashMessage("Action completed!", "success");
    } catch (error) {
      showFlashMessage("Something went wrong!", "danger");
    }
  }

  function setupEventListeners() {
    $("#newClass").on("submit", async (e) => {
      e.preventDefault();

      const formData = $(e.target).serializeArray();
      const data = {};
      $.each(formData, function (_, field) {
        data[field.name] = field.value;
      });

      const dateStringS = data.classStart + "T00:00:00+11:00";
      const dateObjectS = new Date(dateStringS);
      data.classStart = dateObjectS.toString();

      const dateStringE = data.classEnd + "T23:59:59+11:00";
      const dateObjectE = new Date(dateStringE);
      data.classEnd = dateObjectE.toString();

      console.log(data);

      try {
        const response = await apiClient.post("newClass", data);

        $("#newClass")[0].reset();
        $("#newClassModal").modal("hide");

        await loadTasks();
        showFlashMessage(response.message || "Action completed!", "success");
      } catch (error) {
        showFlashMessage("Something went wrong!", "danger");
      }
    });
    $("#editClass").on("submit", async (e) => {
      e.preventDefault();
      const authMessage = await getAuth();
      if (authMessage == "NOT_AUTHENTICATED") {
        return;
      } else if (authMessage == "EDIT_ACCESS_FALSE") {
        return;
      }

      const formData = $(e.target).serializeArray();
      const data = {};
      $.each(formData, function (_, field) {
        data[field.name] = field.value;
      });

      const dateStringS = data["edit-start-date"] + "T00:00:00+11:00";
      const dateObjectS = new Date(dateStringS);
      data["edit-start-date"] = dateObjectS.toString();

      const dateStringE = data["edit-end-date"] + "T23:59:59+11:00";
      const dateObjectE = new Date(dateStringE);
      data["edit-end-date"] = dateObjectE.toString();

      try {
        const response = await apiClient.post("updateSolo", data);
        await loadTasks();
        $(".task-panel").removeClass("active");
        showFlashMessage(response.message, "success");
      } catch (error) {
        showFlashMessage("Something went wrong!", "danger");
      }

      $("#viewMode").show();
      $("#editMode").hide();
      $("#editBtn").show();
      $("#update-btn").hide();
    });
    $("#controlStatus").on("change", async function () {
      const controlStatusVal = $(this).val();

      Cookies.set("controlStatus", JSON.stringify(controlStatusVal), {
        expires: 1 / 12,
        path: "/",
      });

      await loadTasks();
    });
    $("#controlFacilitator").on("change", async function () {
      const controlFacilitatorVal = $(this).val();

      Cookies.set("controlFacilitator", JSON.stringify(controlFacilitatorVal), {
        expires: 1 / 12,
        path: "/",
      });

      await loadTasks();
    });

    $("#toggle").on("click", () => {
      $(".task-table").toggleClass("active"); // show or hide
    });
    // for new
    $("#classType").on("change", function () {
      const val = $(this).val();

      if (val == "Module") {
        $(".div-new-facilitator").hide();
        $("#classFacilitator").removeAttr("required");
      } else {
        $(".div-new-facilitator").show();
        $("#classFacilitator").prop("required", true);
      }
      //$(".task-table").toggleClass("active"); // new class type on change
    });
    //for edit

    $("#edit-type").on("change", function () {
      const val = $(this).val();

      if (val == "Module") {
        $(".div-edit-facilitator").hide();
        $("#edit-classFacilitator").val("");
        $("#edit-classFacilitator").removeAttr("required");
      } else {
        $(".div-edit-facilitator").show();
        $("#edit-classFacilitator").prop("required", true);
      }
      //$(".task-table").toggleClass("active"); // new class type on change
    });
    $("#reset").on("click", async () => {
      Cookies.remove("controlStatus", { path: "/" });
      Cookies.remove("controlFacilitator", { path: "/" });
      Cookies.remove("tpDateRangeStart", { path: "/" });
      Cookies.remove("tpDateRangeEnd", { path: "/" });
      Cookies.remove("tp_sortType", { path: "/" });
      Cookies.remove("tp_sorter", { path: "/" });
      ganttInstance.change_view_mode("Day");

      $("#controlStatus").selectpicker("val", "");
      $("#controlFacilitator").selectpicker("val", "");
      $("#controlviewMode").selectpicker("val", "Day");
      const today = new Date().toISOString().split("T")[0];
      $("#daterange").data("daterangepicker").setStartDate(today);
      $("#daterange").data("daterangepicker").setEndDate(today);

      $(".table-head").each(function () {
        let span = $(this).find("span");
        span.html("");
      });

      await loadTasks();
    });

    $(".table-head").on("click", async function () {
      const target = $(this).attr("id");
      const tp_sorter = Cookies.get("tp_sorter");
      const tp_sortType = Cookies.get("tp_sortType");

      const forChev = "#" + target + "_forChev";

      $(".table-head").each(function () {
        let span = $(this).find("span");
        span.html("");
      });

      if (tp_sorter == target) {
        if (tp_sortType == "ASC") {
          Cookies.set("tp_sortType", "DESC", {
            expires: 1 / 12,
            path: "/",
          });
          $(forChev).html('<i class="fas fa-chevron-up"></i>');
        } else {
          Cookies.set("tp_sortType", "ASC", {
            expires: 1 / 12,
            path: "/",
          });
          $(forChev).html('<i class="fas fa-chevron-down"></i>');
        }
      } else {
        Cookies.set("tp_sorter", target, {
          expires: 1 / 12,
          path: "/",
        });
        Cookies.set("tp_sortType", "ASC", {
          expires: 1 / 12,
          path: "/",
        });
        $(forChev).html('<i class="fas fa-chevron-down"></i>');
      }

      await loadTasks();
    });

    var $leftTable = $("#taskTableBody");
    var $gantt = $(".gantt-container");

    // Scroll Gantt → Scroll Table
    $gantt.on("scroll", function () {
      $leftTable.scrollTop($(this).scrollTop());
    });

    $("#daterange").on("apply.daterangepicker", async function (ev, picker) {
      const start = picker.startDate.format("YYYY-MM-DD");
      const end = picker.endDate.format("YYYY-MM-DD");

      console.log("Start:", start);
      console.log("End:", end);

      Cookies.set("tpDateRangeStart", start, {
        expires: 1 / 12,
        path: "/",
      });

      Cookies.set("tpDateRangeEnd", end, {
        expires: 1 / 12,
        path: "/",
      });

      await loadTasks();
    });

    $("#delete-btn").on("click", async function () {
      const authMessage = await getAuth();
      if (authMessage == "NOT_AUTHENTICATED") {
        return;
      } else if (authMessage == "EDIT_ACCESS_FALSE") {
        return;
      }
      const classId = $(this).attr("data-delete-class-id");
      console.log(`To bedeleted ${classId}`);

      try {
        const response = await apiClient.post("deleteSolo", { id: classId });
        await loadTasks();
        showFlashMessage(response.message, "success");
      } catch (error) {
        showFlashMessage("Something went wrong!", "danger");
      }
      $(".task-panel").removeClass("active");
    });
    $("#closePanel").on("click", () => {
      $(".task-panel").removeClass("active");
      $("#viewMode").show();
      $("#editMode").hide();
      $("#editBtn").show();
    });
    $("#editBtn").on("click", () => {
      $("#viewMode").hide();
      $("#editMode").show();
      $("#editBtn").hide();
      $("#update-btn").show();
    });

    $("#controlviewMode").on("change", function () {
      ganttInstance.change_view_mode($(this).val());
    });

    $("#ganttSection").on("wheel mousewheel DOMMouseScroll", function (e) {
      e.preventDefault();
      e.stopPropagation();
      e.returnValue = false;
    });
    $("#taskTableBody").on("wheel mousewheel DOMMouseScroll", function (e) {
      e.preventDefault();
      e.stopPropagation();
      e.returnValue = false;
    });
  }

  await loadTasks();
  await loadTrainers();
  setupEventListeners();
}

$(async function () {
  const sorter = Cookies.get("tp_sorter");

  if (sorter) {
    const sortType = Cookies.get("tp_sortType");
    const forChev = "#" + sorter + "_forChev";

    if (sortType == "ASC") {
      $(forChev).html('<i class="fas fa-chevron-down"></i>');
    } else {
      $(forChev).html('<i class="fas fa-chevron-up"></i>');
    }
  }

  const controlStatus = Cookies.get("controlStatus");
  if (controlStatus) {
    let saved = [];
    try {
      saved = JSON.parse(controlStatus);
    } catch {
      saved = String(controlStatus).split(",");
    }
    $("#controlStatus").selectpicker("val", saved);
  }

  $("#daterange").daterangepicker({
    autoApply: true,
    locale: {
      format: "YYYY-MM-DD",
    },
  });

  const tpDateRangeStart = Cookies.get("tpDateRangeStart");
  const tpDateRangeEnd = Cookies.get("tpDateRangeEnd");
  $("#daterange").data("daterangepicker").setStartDate(tpDateRangeStart);
  $("#daterange").data("daterangepicker").setEndDate(tpDateRangeEnd);

  const authMessage = await getAuth();
  if (authMessage == "NOT_AUTHENTICATED") {
    return;
  }

  setupGanttApp(authMessage);

  function focusGanttTask(taskId) {
    const taskElement = document.querySelector(
      `.gantt-chart .bar[data-id="${taskId}"]`
    );

    if (taskElement) {
      taskElement.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }

  function findBarElement(taskId) {
    let $bar = $(`.gantt .bar-wrapper[data-id='${taskId}'] .bar`);
    if ($bar.length) return $bar.first();

    $bar = $(`.gantt [data-id='${taskId}'] .bar`);
    if ($bar.length) return $bar.first();

    $bar = $(`[data-id='${taskId}']`).find(".bar");
    if ($bar.length) return $bar.first();

    return null;
  }

  const $tableBody = $("#taskTableBody");
  $tableBody.on("click", ".table-row", function () {
    const $row = $(this);
    const taskId = $row.data("task-id").toString();
    const taskObj = { id: taskId };

    $(".table-row").removeClass("active");
    $(".gantt .bar").removeClass("highlight");

    $row.addClass("active");

    const $bar = findBarElement(taskId);

    if (!$bar || !$bar.length) return;

    $bar.addClass("highlight");

    const barElement = $bar[0];

    if (barElement) {
      barElement.scrollIntoView({
        behavior: "smooth",
        block: "center", // Vertical alignment
        inline: "center", // Horizontal alignment
      });
    }
  });

  $tableBody.on("dblclick", ".table-row", function () {
    const $row = $(this);
    const taskId = $row.data("task-id").toString();
    const taskObj = { id: taskId };

    handleTaskClick(taskObj);
    $(".table-row").removeClass("active");
    $(".gantt .bar").removeClass("highlight");

    $row.addClass("active");

    const $bar = findBarElement(taskId);

    if (!$bar || !$bar.length) return;

    $bar.addClass("highlight");

    const barElement = $bar[0];

    if (barElement) {
      barElement.scrollIntoView({
        behavior: "smooth",
        block: "center", // Vertical alignment
        inline: "center", // Horizontal alignment
      });
    }
  });

  $(document).on("click", ".gantt .bar-wrapper, .gantt .bar", function (e) {
    let taskId =
      $(this).closest("[data-id]").attr("data-id") ||
      $(this).closest(".bar-wrapper").attr("data-id");

    if (!taskId) {
      const wrapper = $(this).closest("[data-id], [data-task-id], [data-task]");
      if (wrapper.length) {
        taskId =
          wrapper.attr("data-id") ||
          wrapper.attr("data-task-id") ||
          wrapper.attr("data-task");
      }
    }
    if (!taskId) return;

    taskId = taskId.toString();

    $(".table-row").removeClass("active");
    $(".gantt .bar").removeClass("highlight");

    const $bar = findBarElement(taskId);
    focusGanttTask(taskId);
    if ($bar) $bar.addClass("highlight");

    const $targetRow = $(`.table-row[data-task-id='${taskId}']`);
    if ($targetRow.length) {
      $targetRow.addClass("active");
      const $tableBody = $("#taskTableBody");
      const rowTop = $targetRow.position().top;
      const rowHeight = $targetRow.outerHeight();
      const containerHeight = $tableBody.innerHeight();
      /*//focus mid area
      const targetScrollTop = Math.max(
        0,
        $tableBody.scrollTop() + rowTop - containerHeight / 2 + rowHeight / 2
      );
      $tableBody.stop().animate({ scrollTop: targetScrollTop }, 250);
      */
    }
  });
});
