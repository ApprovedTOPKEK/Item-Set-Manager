function drop(ev){
	ev.preventDefault();
	var data = ev.dataTransfer.getData("text");
	var eCpy = $("#itemlist").find("div[data-item-name='" + data.replace(/'/g, "\\'") + "']").clone();
	eCpy.append("<br/><span class='decrease'>◄</span><span>1</span><span class='increase'>►</span>");
	eCpy.css({'background-color': "transparent"});
	//eCpy.addClass("removable");
	ev.target.appendChild(eCpy[0]);
}
function allowDrop(ev) {

	if ($(ev.target).hasClass("ItemSetBlockItems")) {
		ev.preventDefault();
	}
}
function drag(ev) {
	ev.dataTransfer.setData("text", $(ev.target).data("itemName"));
}
function dragend(ev){
	$(document).find(".tts1a").removeClass("tts1a");
}

$(document).ready(function () {

	$(document).on("click", "#recMath", function(){
		if(this.hasAttribute("checked")) this.removeAttribute("checked")
		else this.setAttribute("checked", true);
	});

	$(document).on("click", ".upload", function () {

	});
	$(document).on("click", "#save", function(){
		var isid = $("#createidstuff").val();
		var json = new Object();
		json.title = $("#setnamei").val();
		json.map = $("#MapSelect").val();
		json.mode = $("#ModeSelect").val();
		json.champion = $("#champih").val().length==0?"Champion":$("#champih").val();
		json.priority = false;
		json.sortrank = false;
		json.blocks = [];
		$(".realbb").each(function(e){
			var name = $(this).find(".blockinput").val();
			var recMath = this.hasAttribute("checked");
			var items = [];
			$(this).find(".ItemSetBlockItems").find(".ItemSetItem").each(function(){
				var id = $(this).data("itemId");
				var count = parseInt($(this).children().last().prev().text());
				items.push({id: id, count: count});
			});
			json.blocks.push({type: name, recMath: recMath, items: items});
		});
		$.post("mysets.php", {saveset: isid, setdata: JSON.stringify(json)}, function (data) {
		});
		window.location = "mysets.php";
	});
	var emptyBlock = $("#BlockTemplate").clone();
	$("#addnewblock").click(function(){
		emptyBlock.removeAttr("id");
		emptyBlock.addClass("realbb");
		$(this).parent().prev().after(emptyBlock.clone());
	});
	$(document).on('click', ".ItemSetItem", function(){
		//todo
	});
	$(document).on('click', '.decrease', function(){
		var num = $(this).next().text();
		if(num > 1){
			$(this).next().text(num-1);
		}else if(num == 1){
			$(this).parent().fadeOut("slow");
			$(this).parent().remove();
		}
	});
	$(document).on('click', '.increase', function () {
		var num = $(this).prev().text();
		$(this).prev().text(++num);
	});
	$(document).on({
		mouseenter: function () {
			var e = $(this).find(".tts1");
			e.addClass("tts1a");
			e.css({top: $(this).offset().top + $(this).height() - $(document).scrollTop(), left: $(this).offset().left + $(this).width()});


		},
		mouseleave: function(){
			$(this).find(".tts1").removeClass("tts1a");
		}
	}, ".tooltip");

	$(document).on("click", ".delete", function(e){
		$(this).next().fadeIn();
		$(this).next().next().fadeIn();
	});
	$(document).on("click", ".rldel", function(){
		var id = $(this).parent().parent().parent().data("deleteId");
		$("#iss" + id).fadeOut();
		$("#iss" + id).remove();
		$("#ise" + id).remove();
		$.post("index.php", {delete: id}, function (data) {
		});
	});
	$(document).on("click", ".nodel", function(){
		$(this).parent().parent().parent().prev().fadeOut();
		$(this).parent().parent().parent().fadeOut();
	});

	$(document).on("click", ".upvote", function(event){
		event.stopPropagation();
		var p = $(this).parent().parent().parent().parent().parent();
		var id = p.attr("id").split("iss")[1];
		$(this).parent().parent().find(".downvote").fadeOut();
		$(this).fadeOut();
		p.next().fadeOut();
		p.next().remove();
		p.find(".votes").replaceWith("<div id='loading'>Loading</div>");
		$.post("index.php?upvote", {voteid: id}, function (data) {
			p.replaceWith(data);
		});
		return false;
	});
	$(document).on("click", ".downvote", function (event) {
		event.stopPropagation();
		var p = $(this).parent().parent().parent().parent().parent();
		var id = p.attr("id").split("iss")[1];
		$(this).parent().parent().find(".upvote").fadeOut();
		$(this).fadeOut();
		p.next().fadeOut();
		p.next().remove();
		p.find(".votes").replaceWith("<div id='loading'>Loading</div>");
		$.post("index.php?downvote", {voteid: id}, function (data) {
			p.replaceWith(data);
		});
		return false;
	});


	$(".tb").click(function(){
		if($(this).hasClass("no")) $(this).removeClass("no");
		else if($(this).hasClass("yes")){
			$(this).removeClass("yes");
			$(this).addClass("no");
		}else $(this).addClass("yes");

		filterItems();
	});

	$("#itemsearchbar").on("input", function(){
		filterItems();
	});


	function filterItems(){
		$("#itemlist").children("div.ItemSetItem").show();

		if($("#itemsearchbar").val().length > 0) $("#itemlist").children("div").not("[data-item-name^='" + $("#itemsearchbar").val() + "']").hide();

		var tagsYES = [];
		var tagsNO = [];
		$(".tb.yes").each(function (index) {
			tagsYES.push($(this).data("tag"));
		});
		$(".tb.no").each(function (index) {
			tagsNO.push($(this).data("tag"));
		});
		var arr = [];
		console.log($("#itemlist").children("div.ItemSetItem").length);
		$("#itemlist").children("div.ItemSetItem").each(function (index) {
			var j = $(this);
			arr[j.data("itemName")] = j.data("itemTags");
			tagsYES.forEach(function (e) {
				if (j.data("itemTags").indexOf(e) == -1) {
					j.hide();
					return;
				}
			});
			tagsNO.forEach(function (e) {
				if (j.data("itemTags").indexOf(e) != -1) {
					j.hide();
					return;
				}
			});
		});
		console.log(arr);
	}

//	$(document).tooltip();

	$("#logoutb").on("click", function(){
		$("#lof").submit();
	});

	$("#loginb").on("click", function(){
		$(".blackcover").fadeIn();
		$("#Login").fadeIn();
	});

	$("#closeLogin").on("click", function(){
		$("#Login").fadeOut();
		$(".blackcover").fadeOut();
	});

	$("#registerb").on("click", function(){
		$(".blackcover").fadeIn();
		$("#Register").fadeIn();
	});

	$("#closeRegister").on("click", function(){
		$("#Register").fadeOut();
		$(".blackcover").fadeOut();
	});

	$("#main").on("click", "[id^='iss']", function(e){
		if(!$(e.target).hasClass("button2")) { //todo better
			var num = $(this).attr("id").split("iss")[1];
			$("#ise" + num).toggle("slow");
		}
	});

	var currentChamp="Champion";
	var sortRating = false;
	var rating = "desc";
	var sortDate = false;
	var date = "desc";

	function refreshItemsets(){
		$("#Sets").remove();
		$("#main").append("<div id='loading'>Loading</div>");
		var url = "index.php?itemsetChampion=" + currentChamp;
		if (sortRating) url += "&itemsetRating=" + rating;
		if (sortDate) url += "&itemsetDate=" + date;
		$.post(url, {itemsetAjax: true}, function (data) {
			$("#loading").remove();
			$("#main").append(data);
		});
	};

	$(".champbutton").on("click", function () {
		$("[name=" + currentChamp +"]").removeClass("active");
		currentChamp = $(this).attr("name");
		$(this).addClass("active");
		refreshItemsets();
	});

	$(".champbutton2").on("click", function () {
		$("[name=" + currentChamp + "]").removeClass("active");
		currentChamp = $(this).attr("name");
		$(this).addClass("active");
		$("#champih").val($(this).attr("name"));
	});

	$(".arating").on("click", function () {
		$(".rating").removeClass("active");
		$(this).addClass("active");
		sortRating = false;
		refreshItemsets();
	});

	$(".rating").on("click", function () {
		$(".rating").removeClass("active");
		$(".arating").removeClass("active");
		$(this).addClass("active");
		sortRating = true;
		rating = $(this).attr("name");
		refreshItemsets();
	});

	$(".adate").on("click", function () {
		$(".date").removeClass("active");
		$(this).addClass("active");
		sortDate = false;
		refreshItemsets();
	});

	$(".date").on("click", function () {
		$(".date").removeClass("active");
		$(".adate").removeClass("active");
		$(this).addClass("active");
		sortDate = true;
		date = $(this).attr("name");
		refreshItemsets();
	});

	function superbag(sup, sub) {
		sup.sort();
		sub.sort();
		var i, j;
		for (i = 0, j = 0; i < sup.length && j < sub.length;) {
			if (sup[i] < sub[j]) {
				++i;
			} else if (sup[i] == sub[j]) {
				++i;
				++j;
			} else {
				// sub[j] not in sup, so sub not subbag
				return false;
			}
		}
		// make sure there are no elements left in sub
		return j == sub.length;
	}
});