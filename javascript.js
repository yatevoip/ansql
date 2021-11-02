/**
 * Copyright (C) 2008-2014 Null Team
 *
 * This software is distributed under multiple licenses;
 * see the COPYING file in the main directory for licensing
 * information for this specific distribution.
 *
 * This use of this software may be subject to additional restrictions.
 * See the LEGAL file in the main directory for details.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */ 

//keep the docs always visible on page
docs_always_visible = get_cookie('docs_always_visible');

/** 
 * Get Internet Explorer Version
 * @return the version of Internet Explorer or a -1 (indicating the use of another browser)
 */
function getInternetExplorerVersion()
{
	var rv = -1; // Return value assumes failure.
	if (navigator.appName == 'Microsoft Internet Explorer') {
		var ua = navigator.userAgent;
		var re  = new RegExp("MSIE ([0-9]{1,}[\.0-9]{0,})");
		if (re.exec(ua) != null)
			rv = parseFloat( RegExp.$1 );
	}
	return rv;
}

//var ie = getInternetExplorerVersion();

/**
 * Show hidden element 
 * Function tries to set the right  style.display depending on the tag type
 * @param element_id String. Id of the element to be displayed
 */
function show(element_id, display)
{
	if (display==null)
		display = "block";

	if (typeof ie == 'undefined' || ie==null)
		ie = getInternetExplorerVersion();

	var element = document.getElementById(element_id);
	if (element == null)
		return;

	switch (element.tagName.toLowerCase()) {
		case "tr":
		case "img":
		case "input":
		case "select":
		case "iframe":
		case "font":
			element.style.display = (ie > 1 && ie<=9) ? display : "table-row";
			break;
		case "td":
			element.style.display = (ie > 1 && ie<=9) ? display : "table-cell";
			break;
		case "table":
			element.style.display = (ie > 1 && ie<=9) ? display : "table";
			break;
		default:
			element.style.display = display;
	}
}

/**
 * Hide element
 * Function sets style.display to 'none'.
 * @param element_id String. Id of the element to be hidden
 */ 
function hide(element_id)
{
	if (typeof ie == 'undefined' || ie==null)
		ie = getInternetExplorerVersion();

	var element = document.getElementById(element_id);
	if (element == null)
		return;
	element.style.display = "none";
}

/**
 * Show/hide advanced fields in a form and change src and title associated 
 * to the image clicked to perform this action
 * This function is used from editObject() from lib.php is used with fields marked as advanced.
 * @param identifier String. In case there are multiple forms in a single page, 
 * all elements from a form should start with this identified
 */
function advanced(identifier, scroll_to_top)
{
	var myform;
	
	if (identifier!='')
		myform = document.getElementById(identifier);
	else if (document.forms[0])
		myform = document.forms[0];
	else
		myform = null;
		

	var elems = (myform!=null) ? myform.elements : [];
	var elem_name;
	
	console.log("Found "+elems.length+" elements in form.");
	//If checkbox-group is used, all the checkboxes in that group will have the same name (duplicates)
	//Solution: Use this array to skip duplicates
	var unique_form_elems = [];
	
	for (var i=0;i<elems.length;i++) {
		//Added .replace() because the names of mul_select and checkbox-group have suffix []
		elem_name = elems[i].name.replace("[]","");
		
		//Skip if identifier doesn't match or find duplicate form items
		if (identifier.length>elem_name.length && elem_name.substr(0,identifier.length)!=identifier || unique_form_elems.indexOf(elem_name) > -1)
			continue;
		
		var elem = document.getElementById("tr_"+elem_name);
		if (elem == null || elem.style.display == null || elem.style.display == "")
			continue;
		// don't show / hide elements that were triggered_by other fields
		if (elem.getAttribute("trigger") == "\\\"true\\\"" || elem.getAttribute("trigger")=="true")
			continue;
		show_hide("tr_"+elem_name);
		unique_form_elems.push(elem_name);
	}

	// show objtitles that were marked as advanced and are not for objects with specific index (_$index)
	// maximum 10 objtitles 
	for (i=1; i<10; i++) {
		var elem = document.getElementById("tr_" + identifier + i + "_objtitle");
		if (elem == null || elem.style.display == null || elem.style.display == "")
			continue;

		show_hide("tr_" + identifier + i + "_objtitle");
	}

	var img = document.getElementById(identifier+"xadvanced");
	
	if (img!=null && img.tagName=="IMG") {
		var imgsrc= img.src;
		var imgarray = imgsrc.split("/");
		var extension = imgarray[imgarray.length-1].substr(-3);
		if (imgarray[imgarray.length-1] == ("advanced."+extension)) {
			imgarray[imgarray.length-1] = "basic."+extension ;
			img.title = "Hide advanced fields";
		} else {
			imgarray[imgarray.length-1] = "advanced."+extension ;
			img.title = "Show advanced fields";
		}

		img.src = imgarray.join("/");
	} else 
		console.log("advanced() was called, but img is null and tagName='"+img.tagName+"'");
	
	if (true===scroll_to_top)
		scrollToTop();
}

/**
 * Check/Uncheck all checkboxes from form containing the element
 * Usually used from tableOfObjects() from lib.php
 * @param element. 'Select all' checkbox whose checked state dictates the state of the other checkboxes
 */
function toggle_column(element)
{
	var containing_form = parent_by_tag(element, "form");
	if (containing_form==null)
		return;

	for(var z=0; z<containing_form.length;z++) {
		if (containing_form[z].type != 'checkbox')
			continue;
		if (containing_form[z].disabled == true)
			continue;
		containing_form[z].checked = element.checked;
	}
}

/**
 * Retrieve containing tag of certain type where element resides.
 * @param element Object. Element whose containing element you need
 * Note! This is not the id of the element but the element itself
 * @param tagname String. Lowercase value of the tag type of the desired container
 * @return first parent element with specified tagType or null if not found
 */
function parent_by_tag(element, tagname)
{
	if (element==null)
		return;
	while(true) {
		parent_element = element.parentElement;
		if (parent_element==null)
			return null;

		if (parent_element.tagName.toLowerCase()==tagname)
			return parent_element;

		element = parent_element;
	}
}

/*
 * Show/Hide tabs 
 * Used from generic_tabbed_settings() from lib.php
 * @param count_sections Integer. Total number of tabs
 * @param part_ids String. Particle identifying specific elements to be showed/hidden.
 * Defaults to ''
 */
function show_all_tabs(count_sections,part_ids)
{
	if (part_ids==null)
		part_ids = "";
	var img = document.getElementById("img_show_tabs"+part_ids);
	if (img.src.substr(-12)=="/sm_show.png")
		img.src = "images/sm_hide.png";
	else
		img.src = "images/sm_show.png";
	var i;
	for (i=1; i<count_sections; i++) {
		section_tab = document.getElementById("tab_"+part_ids+i);
		section_fill = document.getElementById("fill_"+part_ids+i);
		if (section_tab==null) {
			alert("Don't have section tab for "+part_ids+i);
			continue;
		}
		if (section_tab.style.display=="") {
			section_tab.style.display = "none";
			if (section_fill!=null)
				section_fill.style.display = "";
		} else {
			section_tab.style.display = "";
			if (section_fill!=null)
				section_fill.style.display = "none";
		}
	}
}

/**
 * Show a section when clicked - when tabs are used.
 * Function sets classname to  "section_selected basic "+custom_css+"_selected" for first section when selected
 * or "section_selected "+custom_css+"_selected" in case another one is selected
 * and to "section basic "+custom_css when first section is closed
 * and to "section "+custom_css when another section is closed
 * @param section_index Integer. Number of the section to be shown. 
 * @param count_sections Integer. Total number of tabs
 * @param part_ids String. Particle identifying specific elements to be showed/hidden.
 * Defaults to ''
 * @param custom_css String. Name of custom css class
 */
function show_section(section_index,count_sections,part_ids,custom_css)
{
	if (part_ids==null)
		part_ids="";
	if (custom_css==null)
		custom_css="";

	var i, section_div, section_tab;
	for (i=0; i<count_sections; i++) {
		section_tab = document.getElementById("tab_"+part_ids+i);
		section_div = document.getElementById("sect_"+part_ids+i);
		if (section_tab==null) {
			console.log("Don't have section tab for "+i);
			continue;
		}
		if (section_div==null) {
			console.log("Don't have section div for "+i);
			continue;
		}
		if (i==section_index) {
			if (i==0)
				section_tab.className = "section_selected basic "+custom_css+"_selected";
			else
				section_tab.className = "section_selected "+custom_css+"_selected";
			section_div.style.display = "";
		} else {
			cls = section_tab.className;
			if (cls.substr(0,16)=="section_selected") {
				if (i==0)
					section_tab.className = "section basic "+custom_css;
				else
					section_tab.className = "section "+custom_css;
				section_div.style.display = "none";
			}
		}
	}
}

/**
 * Show/hide comment associated to a field
 * @param id String. Id of the object whose comment should be shown
 */
function show_hide_comment(id)
{
	show_hide("comment_"+id);
}

/**
 * Show element in iframe.
 * @param category_id String. Category Id of the element to be shown
 * @param comment_id String. Comment Id of the element to be shown
 */
function show_docs(category_id, comment_id)
{
	if (typeof close_search == "function") {
		close_search();
	}
	
	if (docs_always_visible == "" || docs_always_visible == "false") {
		docs_always_visible = true;
		set_cookie('docs_always_visible', docs_always_visible);
	}

	/* set reference_id with the id found in iframe html*/
	var iframe = document.getElementById('iframe_param');
	var reference_id = category_id+"_id";
	var iframe_doc = get_iframe_doc(iframe);
	if (iframe_doc.getElementById(comment_id+"_id"))
		reference_id = comment_id+"_id";

	console.log("Reference_id comment: " + reference_id + ", comment_id: " + comment_id);

	document.getElementById("page_id").style.width="78%";
	if (iframe_doc.getElementById(reference_id))
		iframe_doc.getElementById(reference_id).className = "docs_focus";

	if (document.last_comment_id && iframe_doc.getElementById(document.last_comment_id+"_id") &&
			document.last_comment_id+"_id" != reference_id)
		if (iframe_doc.getElementById(document.last_comment_id+"_id").parentElement.className == "sect_subtitle")
			iframe_doc.getElementById(document.last_comment_id+"_id").className = "";
		else
			iframe_doc.getElementById(document.last_comment_id+"_id").className = "docs_lost_focus";

	/* set the new last_reference_id */
	document.last_comment_id = category_id;
	if (iframe_doc.getElementById(comment_id+"_id"))
		document.last_comment_id = comment_id;

	show("iframe_param");
	show("docs_iframe");
	show("docs_x");
	show("docs_expand_id");
	
	var docs_expand_elem = document.getElementById("docs_expand_id");
	
	if (document.getElementById("iframe_param").style.display == "none") {
		document.getElementById("iframe_param").className = "params_explanation_hidden";
		document.getElementById("docs_x").className = "docs_close_hidden";
		if (docs_expand_elem !== null)
			docs_expand_elem.className = "docs_expand_hidden";
	} else {
		document.getElementById("docs_iframe").className = "docs";
		document.getElementById("iframe_param").className = "params_explanation";
		document.getElementById("docs_x").className = "docs_close";
		if (docs_expand_elem !== null)
			docs_expand_elem.className = "docs_expand";
	}

	/* find if the element exist in the iframe and scroll the contents
	 * of the document window to the specified horizontal and vertical position*/
	var iframe_win = get_iframe_win(iframe);
	var element = iframe_doc.getElementById(reference_id);
	if (element) 
		iframe_win.scroll(0, get_offset_top(element));

	/*position the scroll on top of the page*/
	window.scroll(0,0);
}

/**
 * Show/hide element in iframe.
 * @param category_id String. Category Id of the element to be shown/hidden
 * @param comment_id String. Comment Id of the element to be shown/hidden
 */
function show_hide_docs(category_id, comment_id)
{
	if (docs_always_visible == "" || docs_always_visible == "false") {
		docs_always_visible = true;
		set_cookie('docs_always_visible', docs_always_visible);
	}

	/* set reference_id with the id found in iframe html*/
	var iframe = document.getElementById('iframe_param');
	var reference_id = category_id+"_id";
	var iframe_doc = get_iframe_doc(iframe);
	if (iframe_doc.getElementById(comment_id+"_id"))
		reference_id = comment_id+"_id";

	document.getElementById("page_id").style.width="78%";
	if (iframe_doc.getElementById(reference_id))
		iframe_doc.getElementById(reference_id).style.color = "red";
	if (document.last_comment_id && iframe_doc.getElementById(document.last_comment_id+"_id"))
		iframe_doc.getElementById(document.last_comment_id+"_id").style.color = "black";

	/* show or hide the element depending on the last_reference_id value*/
	if (comment_id == document.last_comment_id) {
		show_hide("iframe_param");
		show_hide("docs_x");

		if (document.getElementById("iframe_param").style.display == "none"){
			document.getElementById("page_id").style.width="100%";
		}
	
		return;
	}
	/* set the new last_reference_id */
	document.last_comment_id = comment_id;

	show("iframe_param");
	show("docs_iframe");
	show("docs_x");
	if (document.getElementById("iframe_param").style.display == "none") {
		document.getElementById("iframe_param").style.className = "iframe_explanation_hidden";
		document.getElementById("docs_x").style.className = "docs_close_hidden";
	} else {
		document.getElementById("iframe_param").style.className = "iframe_explanation";
		document.getElementById("docs_x").style.className = "docs_close";
	}

	/* find if the element exist in the iframe and scroll the contents
	 * of the document window to the specified horizontal and vertical position*/
	var iframe_win = get_iframe_win(iframe);
	var element = iframe_doc.getElementById(reference_id);
	if (element) 
		iframe_win.scroll(0, get_offset_top(element));

	/*position the scroll on top of the page*/
	window.scroll(0,0);
}

/**
 * Resize iframe to fit page size and optionally scroll iframe content to the specified element.
 * @param obj Object. The object to be resized based on parent page element with id "page_id" or on window size.
 * @param scroll_to String. Id of the element to scroll to. Default is null.
 */
function resize_iframe(obj, scroll_to = null) 
{
	var win_height = window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight;
	var pag_scroll = document.getElementById("page_id").scrollHeight-47;
	
	if ( pag_scroll < win_height-250) {
		obj.style.height = win_height-250 + 'px';
	} else {
		obj.style.height = pag_scroll + 'px';
	}
	
	if (scroll_to === null)
		return;
	
	var iframe = document.getElementById('iframe_param');
	var iframe_doc = get_iframe_doc(iframe);
	var iframe_win = get_iframe_win(iframe);
	var element = iframe_doc.getElementById(scroll_to);
	if (element) 
		iframe_win.scroll(0, get_offset_top(element));
}

/**
 * Resize iframe and optionally scroll it to the specified element.
 * @param obj Object. The object to be resized.
 * @param elem String. The Id of the iframe element based on which the iframe height is adjusted. If not found, iframe height is set to 110px.
 * @param scroll_to String. Id of the iframe element to scroll to. Default is null.
 */
function resize_left_iframe(obj, elem, scroll_to = null)
{
	var iframe = document.getElementById('left_iframe');
	var iframe_doc = get_iframe_doc(iframe);
	if (iframe_doc.getElementById(elem))
		obj.style.height = iframe_doc.getElementById(elem).scrollHeight + 'px';
	else
		obj.style.height = "110px";
	
	if (scroll_to === null)
		return;
	
	var iframe_win = get_iframe_win(iframe);
	var element = iframe_doc.getElementById(scroll_to);
	if (element) 
		iframe_win.scroll(0, get_offset_top(element));
	
}

function closeFrame() 
{
	docs_always_visible = false;
	set_cookie('docs_always_visible', docs_always_visible);
	if (isMissing(document.getElementById("docs_iframe"))) {	
		return;
	}
	document.getElementById("docs_iframe").style.display="none";
	document.getElementById("page_id").style.width="100%";
}

function set_cookie(cname, cvalue)
{
	cvalue = encodeURIComponent(cvalue);
	document.cookie = cname + "=" + cvalue + "; path=/";
}

function get_cookie(cname)
{
	var name = cname + "=";
	var ca = document.cookie.split(';');
	for (var i=0; i<ca.length; i++) {
		var c = ca[i];
		while (c.charAt(0)==' ')
			c = c.substring(1);
		if (c.indexOf(name) == 0)
			return decodeURIComponent(c.substring(name.length,c.length));
	}
	return "";
}

/**
 * Returns the Document object generated by a iframe element
 * @param iframe Object. The iframe document object
 */ 
function get_iframe_doc(iframe)
{
	return iframe.contentDocument || iframe.contentWindow.document;
}

/**
 * Returns the Document object generated by an iframe element
 * @param iframe Object. The iframe document object
 */
function get_iframe_win(iframe)
{
	return iframe.contentWindow || iframe;
}

/**
 * Returns the vertical position of a given object
 * @param obj.
 */ 
function get_offset_top(obj)
{
	var ret = 0;
	while (obj.offsetParent) {
		ret += obj.offsetTop;
		obj = obj.offsetParent;
	}
	return ret;
}

/**
 * Show/hide element. Changes visibility of the element
 * @param element_id String. Id of the element to to shown/hidden
 */ 
function show_hide(element_id)
{
	var element = document.getElementById(element_id);
	if (element==null)
		return;

	if (element.style.display=="none")
		show(element_id);
	else
		hide(element_id);
}

/**
 * Make HTTP request to specified url.
 * encodeURI function is used on url before using @make_api_request function
 * @param url String. Where to make request to
 * @param cb Callback. If set, call it passing response from HTTP request as argument
 * @param async Bool. Default true. If set, specifies if the request should be handled asynchronously or not. 
 * @param already_encoded Bool. Default false. If true, we assume url was already encoded, otherwise encodeURI will be run on @param url
 * Default async is true (asynchronous) 
 */
function make_request(url, cb, async, already_encoded)
{
	if (typeof async === "undefined")
		async = true;
	if (typeof already_encoded === "undefined")
		already_encoded = false;

	if (!already_encoded)
		url = encodeURI(url);

	make_api_request(url, cb, async);
}

/**
 * Make HTTP request to specified url. 
 * If callback cb is defined, call with by passing response from HTTP request as parameter
 * Use @make_reques function to make sure url is encoded since this function assumes it already was encoded.
 * @param url String. Where to make request to
 * @param cb Callback. If set, call it passing response from HTTP request as argument
 * @param aync Bool. If set, specifies if the request should be handled asynchronously or not. 
 * Default async is true (asynchronous)
 */
function make_api_request(url, cb, async, request_type, ignore_not_successful)
{
	var xmlhttp = GetXmlHttpObject();
	if (xmlhttp == null) {
		alert("Your browser does not support XMLHTTP!");
		return;
	}
	
	if (typeof ignore_not_successful === 'undefined')
		ignore_not_successful = false;
	    
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4) {
			if (ignore_not_successful && (xmlhttp.status==0 || xmlhttp.status>=400)) 
				return;
			
			var response = xmlhttp.responseText;
			if (response)
				remove_spinner();
			if (typeof cb != 'undefined' && cb !== null) 
				call_function(cb,response);
		}
	};
	if (typeof async === 'undefined')
		async = true;

	if (typeof request_type === 'undefined')
	    request_type = "POST";
	
	var pos = url.indexOf('?');
	var request_fields = url.substr(pos+1);
	if (pos!=-1)
		url  = url.substr(0,pos);

	xmlhttp.open(request_type, url, async);
	xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	xmlhttp.send(request_fields);
}
function get_current_base_url()
{  
    return window.location.protocol + "//" + window.location.hostname + ":" + window.location.port;
}

function get_current_url()
{
    var base_url = get_current_base_url();
    return  base_url + window.location.pathname;
}

/**
 * Make callback 
 * @param cb Callback. Name of the function to be called
 * The callback can be set as object:
 * cb={name:name_func, param:value}
 * @param param. Parameter to be passed when calling cb
 */
function call_function(cb, param)
{
	if (cb && typeof(cb) === "function") {
		// execute the callback, passing parameters as necessary
		cb(param);
	} else if (cb && typeof(cb) === "object" && typeof(cb.name) === 'function') {
		cb.name(param, cb.param);
	} else
		console.error("Trying to call invalid callback "+cb.toString()+", type for it is: "+typeof(cb));
}

/**
 * Retrieve object used to make HTTP request.
 * @returns object. Function returns new XMLHttpRequest or ActiveXObject("Microsoft.XMLHTTP") depending on browser or null if none of the two is available
 */
function GetXmlHttpObject()
{
	if (window.XMLHttpRequest)
	{
		/* code for IE7+, Firefox, Chrome, Opera, Safari*/
		return new XMLHttpRequest();
	}
	if (window.ActiveXObject)
	{
		/* code for IE6, IE5*/
		return new ActiveXObject("Microsoft.XMLHTTP");
	}
	return null;
}
 
function insert_spinner()
{
	if (document.getElementById("spinner_id") != null)
		show("spinner_id");
}

function remove_spinner()
{
	hide("spinner_id");
}


/**
 * Check if required fields were set.
 * The required_fields global variable is checked
 * Ex: required_fields={"username":"username", "contact_info":"Contact information"}
 * "contact_info" is the actual field_name in the form while "Contact information" is what the user sees associated to that form element
 * @return Bool. True when required fields are set, false otherwise 
 * If required_fields is undefined then function returns true and a message is logged in console
 */
function check_required_fields()
{
	if (typeof(required_fields) === "undefined") {
		console.log("The required fields are not defined!");
		return false;
	}

	var err = "";
	// variable required_fields is a global array 
	// it is usually created from method requiredFieldsJs() from Wizard class

	var field_name, field_value, field, tr_field;
	for (field_name in required_fields) {
		tr_field = document.getElementById("tr_"+field_name);
		if ((tr_field.getAttribute("trigger") == "\\\"true\\\"" || tr_field.getAttribute("trigger")=="true") && tr_field.style.display=="none")
			continue;

		if (document.getElementById(field_name)==null) {
			console.log("The required field: "+field_name+" has not an ID defined!");
			return false;
		}

		field_value = document.getElementById(field_name).value;
		if (field_value=="")
			err += "Please set "+required_fields[field_name]+"! ";
	}
	if (err!="") {
		error(err);
		return false;
	}
	return true;
}

/**
 * Display error. Function currently used alert.
 * @param error String. Error to be displayed to the user
 */
function error(error)
{
	alert(error);
}

/**
 * Verify if value is numeric. Used isNaN js function.
 * @param val String. Value to be checked
 * @return Bool
 */
function is_numeric(val)
{
	return !isNaN(val);  
}

/**
 * Delete element
 * @param id String. Id of the tag to remove
 */
function delete_element(id)
{
	var obj = document.getElementById(id);
	if (obj)
		obj.parentNode.removeChild(obj);
}

/**
 * Change object/tag id
 * @param id String. Current id of the tag
 * @param new_id String.
 */
function set_id_obj(id, new_id)
{
	var obj = document.getElementById(id);
	if (obj)
		obj.id = new_id;
}

/**
 * Set value of object/tag
 * @param id String. Id of the tag 
 * @param val String. Value to be set in tag with specified id
 */
function set_val_obj(id, val)
{
	var obj = document.getElementById(id);
	if (obj)
		obj.value = val;
}

/**
 * Submit form 
 * @param formid String. Id of the form to submit
 */
function submit_form(formid)
{
	var form_to_submit = document.getElementById(formid);
	if (form_to_submit)
		form_to_submit.submit();
}

/** 
 * Retrieve selected value from dropdown
 * @param id_name String. The id of the select tag 
 * @return String with selected option or null if tag with it is not found or no value is selected
 */
function get_selected(id_name)
{
	var selector_obj = document.getElementById(id_name);
	if (selector_obj==null)
		return null;
	var sel = selector_obj.options[selector_obj.selectedIndex].value || selector_obj.options[selector_obj.selectedIndex].text;
	return sel;
}

/** 
 * Sets innerHTML for specific tag
 * @param id String. Id of the tag to set content into
 * @param html String. The content to be set in the tag specified by id
 */
function set_html_obj(id, html)
{
	var obj = document.getElementById(id);
	if (obj)
		obj.innerHTML = (html == null) ? "" : html;
}

/**
 * Show fields after 'Add another ...' link is pressed. 
 * This fields must already exist and be hidden.
 * When made using display_pair() you must set triggered_by => "" for the fields
 * Function will show all fields ending in link_index from form 
 * and will hide clicked link and display the one with the next index
 * @param link_index Integer. The index that unites fields to be show as part of another object
 * @param link_name String. Name of the add link. Ex: add, add_contact
 * @param hidden_fields Array. Contains the name of the input fields of type 'hidden' 
 * @param level_fields Bool. If true, the fields in the FORM are on more levels
 * @param name_obj_title String. Common piece of the name of the objtitle field for this level of objects
 * @param border_elems Object. {"start":"", "end":""}. Name between which we start showing elements. Used when you have more types of objects so _index is not unique  
 * @param advanced_id String. The ID set in html of the 'Advanced/Basic' button. If undefined the ID set in Advanced/Basic button from class Wizard will be used.
 * Ex: {"end":name_border} // between start of the form and name_border
 * Ex: {"start":name_border} // between name_border and end of form
 * Ex: {"start":border1, "end":border2} // between border1 and border2 
 */
function fields_another_obj(link_index, link_name, hidden_fields, level_fields, name_obj_title, border_elems, multiple_subtitle, advanced_id)
{
	console.log("Entered fields_another_obj() ", arguments);

	// global variable needed by wizard_advanced
	current_object_index = link_index;

	if (!is_numeric(link_index)) {
		console.error("Called fields_another_obj with non numeric param link_index: "+link_index);
		return;
	}

	if (name_obj_title==undefined)
		name_obj_title = "objtitle";

	var element_name;

	// this is the link that was clicked and should be hidden
	var current_link_id = link_name+(link_index-1);
	if (document.getElementById(current_link_id)==null)
		// maybe previous index is not with build by decresing 1 but by removing the last digit: _ or _1 are current_link_id for _2 or _12
		current_link_id = link_name+link_index.substr(0,link_index.length-1);

	hide(current_link_id);

	// retrieve all elements from same form as the clicked link
	var parentform = parent_by_tag(document.getElementById(current_link_id),"form");
	if (parentform==null) {
		console.error("Can't retrieve parent for for element with id " + current_link_id);
		return;
	}
	elems = parentform.elements;

	// see if there are advanced fields (check button -- it's displayed only when there are fields marked as advanced)
	if (advanced_id==undefined)
		advanced_id = "advanced_wiz_button";
	var show_advanced = document.getElementById(advanced_id);
	if (show_advanced!=null) {
		//	console.log("innerHTML advanced button:'"+show_advanced.innerHTML+"'");
		//	show_advanced = (show_advanced.innerHTML=="Advanced") ? false : true;
		console.log("innerHTML advanced button:'"+show_advanced.src+"'");
		var str = show_advanced.src;
		show_advanced = (str.indexOf("advanced")!=-1) ? false : true;
	} else
		show_advanced = false;
	console.log("Show advanced: "+show_advanced);

	// show objtitle, if defined
	show("tr_" + name_obj_title + link_index);

	console.log("level_fields="+level_fields);
	if (level_fields!=undefined && level_fields==true)
		show("tr_"+name_obj_title+"_level_"+link_index);

	if (multiple_subtitle!=undefined && multiple_subtitle==true) {
		var id_to_show;
		for (var k=0; k<10; k++) {
			if (k==0)
				id_to_show = "tr_"+name_obj_title+"_level_"+link_index;
			else
				id_to_show = "tr_"+name_obj_title+"_level"+k+"_"+link_index;
			show(id_to_show);
			console.log("Tried to show:'"+id_to_show+"'");
		}
	}

	var id_tr_element, tr_element;
	var have_start = true;
	var have_end = false;

	if (border_elems!=undefined && border_elems.start!=undefined)
		have_start = false;

	for (var i=0; i<elems.length; i++) {

		element_name = elems[i].name;
		if (element_name.substr(-2)=="[]")
			element_name = element_name.substr(0,element_name.length-2);

		if (!have_start && elems[i].name==border_elems.start)
			have_start = true;

		if (border_elems!=undefined && border_elems.end!=undefined && border_elems.end==elems[i].name)
			have_end = true;

		if (!have_start || have_end) {
			//console.log("Skipping "+elems[i].name+": have_start="+have_start+" have_end="+have_end);
			continue;
		}

		// we assume that elements in form have the same "id" and "name"
		// the containing tr is built by concatenanting "tr_" + element_id
		id_tr_element = "tr_" + element_name;
		
		var current_elem_index = id_tr_element.substr(element_name.length+2, id_tr_element.length);
		//for index > 9
		if (!isNaN(id_tr_element.substr(element_name.length+1, id_tr_element.length))) {
			current_elem_index = id_tr_element.substr(element_name.length+1, id_tr_element.length);
		}

		// if form fields are displayed on more levels
		// split the form elements name by '_' to get their id 
		// and skip the elements that don't have to be displayed
		// otherwise just display links ending in link_index
		if (level_fields!=undefined && level_fields==true) {
			var index_arr = id_tr_element.split("_");
			if (index_arr[index_arr.length-1] !=link_index) 
				continue;
		} else if (current_elem_index!=link_index)
			continue;

		tr_element = document.getElementById(id_tr_element);

		// this field is advanced -> display it only if user already requested to see advanced fields
		if (tr_element.getAttribute("advanced")=="true" && show_advanced==false)
			continue;

		if (hasClass(tr_element, 'hidden_tr'))
			continue;

		show(id_tr_element);
	}

	// this is the next link that should be displayed
	show("tr_"+link_name+link_index);

	//if hidden_fields was set, append it to the other already set
	if (hidden_fields != undefined) {
		for (var name in hidden_fields) {
			var input = document.createElement("INPUT");
   			input.setAttribute("type", "hidden");
			input.setAttribute("name", ''+hidden_fields[name]+'');
			input.setAttribute("value", 'off');
			tr_element_hidden = document.getElementById("tr_hidden_fields");
			tr_element_hidden.appendChild(input);
		}
	}

	// show objtitles that were marked as advanced and are not for objects with specific index (_$index)
	// Ex: 1_objtitle1, 1_objtitle2,   -- first number is the nr of the objtitle, last is the object index
	// 2_objtitle1, 2_objtitle2
	// maximum 10 objtitles 
	for (i=1; i<=10; i++) {
		var elem = document.getElementById("tr_" + i + "_objtitle" + link_index);
		if (elem == null || elem.style.display == null || elem.style.display == "" || (elem.getAttribute("advanced")=="true" && show_advanced==false))
			continue;

		show("tr_" + i + "_objtitle" + link_index);
	}
}

/**
 * Builds part of a link from the fields in a form
 * Values set in form field are escaped with 'encodeURIComponent' before concatenanting
 * @param form_name String. Name of the form where to take the elements from
 * @return String. Part of a link
 * Ex: ?name=Larry&phone_number=4034222222&zipcode=50505
 */ 
function link_from_fields(form_name) 
{
	var form_obj = document.forms[form_name];
	if (form_obj==null)
		return null;
	var qs = '';
	for (e=0;e<form_obj.elements.length;e++) {
		if (form_obj.elements[e].name!='') {
			if (form_obj.elements[e].type=="checkbox" && form_obj.elements[e].checked!=true)
				continue; 
			if (form_obj.elements[e].type!="select-multiple") {
				qs += (qs=='')?'?':'&';
				qs += form_obj.elements[e].name+'='+fixedEncodeURIComponent(form_obj.elements[e].value);
			} else {
				for (var i = 0; i < form_obj.elements[e].options.length; i++) {
					if (form_obj.elements[e].options[i].selected) {
						qs += (qs=='')?'?':'&';
						qs +=  form_obj.elements[e].name+"="+form_obj.elements[e].options[i].value;
					}
				}
			}
		}
	}

	console.log("Result link_from_fields: "+qs);
	return qs;
}

// Be more stringent in adhering to RFC 3986 (which reserves !, ', (, ), and *), even though these characters have no formalized URI delimiting uses.
// Additional info at https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/encodeURIComponent
function fixedEncodeURIComponent(str) 
{
	return encodeURIComponent(str).replace(/[!'()*]/g, function(c) {
		return '%' + c.charCodeAt(0).toString(16);
	});
}

/**
 * Removed value from array
 * Note! Only the first found value is removed
 * @param val. Value to be removed
 * @param arr Array. Array to remove value from
 */
function removeArrayValue(val,arr)
{
	if (!val)
		return;
	for (var i=0; i<arr.length; i++) {
		if (arr[i]==val) {
			arr.splice(i, 1);
			break;
		}
	}
}

/**
 * Adds div element with specified id, content and css class in another element
 * Element is inserted at the beggining
 * @param parent_id String. Id of the parent element in which the new element will be added
 * @param element_id String. Id of the new element that will be added
 * @param html String. Content to be set inside new div
 * @param css_class String. CSS class(es) for the new div
 */
function add_element_with_class(parent_id, element_id, html, css_class)
{
	parent_elem = document.getElementById(parent_id);
	if (parent_elem==null)
		return;
	newDiv = document.createElement("div");
	newDiv.setAttribute("id", element_id);
	newDiv.innerHTML = html;
	if (css_class)
		newDiv.className = css_class;
	parent_elem.parentNode.insertBefore(newDiv, parent_elem);
}

/**
 * Adds div element with specified id, content and css class in another element
 * Element is inserted at the beggining
 * @param parent_id String. Id of the parent element in which the new element will be added
 * @param element_id String. Id of the new element that will be added
 * @param html String. Content to be set inside new div
 */
function add_element(parent_id, element_id, html)
{
	add_element_with_class(parent_id, element_id, html, null);
}

/**
 * Used when user selects a 'Custom' option from a dropdown
 * Adds input field with id 'custom_'+id of the dropdown
 * @param custom_value String. Value to set in the newly added field
 * @param dropdown_id String. Id of the dropdown 
 */
function custom_value_dropdown(custom_value,dropdown_id,js_on)
{
	// the id of the custom field that will be added
	var custom_id = "custom_"+dropdown_id;
	var custom_field = document.getElementById(custom_id);

	var selected = get_selected(dropdown_id);
	if (selected=="Custom" || selected.substr(0,6)=="Custom") {
		var dropdown = document.getElementById(dropdown_id);
		var parent_td = dropdown.parentNode;
		if (custom_field==null) {
			parent_td.appendChild(document.createElement("br"));
			// custom_field wasn't added in the page => add it here
			var input = document.createElement("INPUT");
			input.setAttribute("type", "text");
			input.setAttribute("class", "margintop");
			input.setAttribute("id", ''+custom_id+'');
			input.setAttribute("name", ''+custom_id+'');
			if (js_on!=undefined)
				input.setAttribute("onchange", ''+js_on+'');
			if (custom_value!=null)
				input.setAttribute("value", ''+custom_value+'');
			parent_td.appendChild(input);
		} else {
			// custom_field is already in the page => display it
			custom_field.style.display = "";
		}

	} else if (custom_field!=null && custom_field.style.display!="none")
		custom_field.style.display = "none";
}

// Toggle menu build with TabbedSettings class. Show/hide (Advanced/Basic) tabs and buttons
function toggle_menu()
{
	//var i = (typeof(open_tabs)==="undefined") ? 1 : open_tabs;
	var i = (open_tabs==undefined) ? 1 : open_tabs;
	var current_state = document.getElementById("section_"+i).style.display;
	var next_state = (current_state!="none") ? "none" : "table-cell";
	var toggle_content = (current_state!="none") ? "Advanced >>" : "<< Basic";
	var menu_tab;

	menu_tab = document.getElementById("menu_fill");
	if (menu_tab) 
		menu_tab.style.display = current_state;

	while(true) {
		menu_tab = document.getElementById("section_"+i);
		if (menu_tab==null)
			break;
		menu_tab.style.display = next_state;
		i++;
	}
	set_html_obj("menu_toggle",toggle_content);

	if (subsections_advanced.length) {
		for (var i=0; i<subsections_advanced.length; i++) {
			if (toggle_content == "Advanced >>") {
				document.getElementById('tab_'+subsections_advanced[i]).style.display = "none";
				document.getElementById('space_'+subsections_advanced[i]).style.display = "none";
			} else {
				document.getElementById('tab_'+subsections_advanced[i]).style.display = "table-cell";
				document.getElementById('space_'+subsections_advanced[i]).style.display = "table-cell";
			}
		}
	}
}

/**
 * Clean columns when php generic_search() function is used
 */
function clean_cols_search(readonly)
{
	var col_value = document.getElementById('col_value');
	if (readonly == true) {
		var col = document.getElementById('col');
		if (col.value == "") 
			col_value.readOnly = true;
		else
			col_value.readOnly = false;
	}
	
	if (col_value!=null)
		col_value.value = null; 

	var total=document.getElementById('total'); 
	if (total!=null) 
		total.value = null;
	return true;
}

/**
Calculate the following parameters:
On Radio in EnodeB screen:
	* Pusch.RefSignalGroup: cellID % 30
	
On Access Channels :: PRACH screen:
	* Prach.RootSequence: cellID + (random value in range 0..334)
	* Prach.FrequencyOffset: 0
	
On Access Channels :: PUSCH screen:
	* Pusch.CyclicShift: cellID % 8
	
On Access Channels :: PUCCH screen:
Old implementation:
	* Pucch.CsAn: dependency on Pucch.Delta if 1 => cellID % 8
	* if 2=> 2*(cellID%8); if 3 => 3*(cellID%3)
Now the default value is 7.
	* ResourceAllocationOffset: cellID + random() % (2047-503)
*/
function set_cellid_dependencies()
{
	var nid1 = document.getElementById("NID1").value;
	var nid2 = document.getElementById("NID2").value;

	if (!nid1 || !nid2)
		return;

//	var pucchDelta = document.getElementById("Pucch.Delta").value;
	var cellid = 3*parseInt(nid1) + parseInt(nid2);
	var rootSequenceIndex = cellid + get_rand_int(0, 334); 
//	var prachFreqOffset = cellid % 95;
	var groupAssignmentPUSCH = cellid % 30;
	var cyclicShift = cellid % 8;

	var resourceAllocationOffset = cellid;
/*	if (pucchDelta == 1)
		var pucchCsAn = cellid%8;
	else if (pucchDelta == 2)
		var pucchCsAn = 2*(cellid%4);
	else if (pucchDelta == 3)
		var pucchCsAn = 3*(cellid%3);
*/
	document.getElementById("rootSequenceIndex").value = rootSequenceIndex;
//	document.getElementById("prach-FreqOffset").value = prachFreqOffset;
	document.getElementById("groupAssignmentPUSCH").value = groupAssignmentPUSCH;
	document.getElementById("cyclicShift").value = cyclicShift;
	document.getElementById("n1Pucch-An").value = resourceAllocationOffset;
//	document.getElementById("Pucch.CsAn").value = pucchCsAn;
}

/**
 * Returns a random integer between min (inclusive) and max (inclusive)
 * Using Math.round() will give you a non-uniform distribution!
 */
function get_rand_int(min, max) 
{
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

/**
 * This function triggers the popup on a javascript event set on the html element (ex: button onclick, link onclick)
 * It uses make_request to make request to a link and call the js function that displays the popup 
 * @param popup_request_object Object contains: 
 * link - where to make request for content
 * cb_api_response  - this function will be called to display the popup (if not set, default function display_popup will be called)
 * cb_close_button  - this function will be called when user clicks on close button to close the popup (if not set, default function close_popup will be called)
 * cb_close_button_params - set the params for the function set in cb_close_button (if not set, function set in cb_close_button will be called with no parameters)
 * 
 * Ex: obj = {"link":"pages.php?method=card_scan","cb_api_response":display_popup,"cb_close_button":minimize_success,"cb_close_button_params":{"method":"card_scan"}}
 */
function request_popup(popup_request_object)
{
	var callback;
	
	if (typeof popup_request_object.cb_api_response == 'undefined' || popup_request_object.cb_api_response==null)
		callback = display_popup;
	else
		callback = popup_request_object.cb_api_response;
	
	make_request(popup_request_object.link, {name:callback, param:popup_request_object});
}

/**
 * Displays the popup
 * @param html_content - returned content from link request
 * @param popup_request_object Object . The same object set in request_popup.
 */
function display_popup(html_content,popup_request_object)
{
	current_y = window.pageYOffset;

	var overlay = document.getElementById('maximize_overlay');
	var container = document.getElementById('maxedcontainer');

	if (container==null || overlay==null) {
		alert("Coding error. Please add overlay and container divs to add anon visitor pop up in.");
		return;
	}
	
	container.innerHTML = html_content;
	
	if (document.getElementById("closebut")) {
	    if (typeof popup_request_object.cb_close_button != 'undefined' || popup_request_object.cb_close_button!=null) {
		    if (popup_request_object.cb_close_button == false)
			    document.getElementById("closebut").style.display = "none";
		    if (typeof popup_request_object.cb_close_button_param != 'undefined' || popup_request_object.cb_close_button_param!=null){
			    document.getElementById("closebut").onclick = function() {
									    // params will be an array that contains the object properties
									    var params = Object.values(popup_request_object.cb_close_button_param);
									    var cb = popup_request_object.cb_close_button;
									    //call the function with multiple params
									    cb.apply(null,params);
								    };
		    }
		    else		
			    document.getElementById("closebut").onclick = popup_request_object.cb_close_button;
	    } else
		    document.getElementById("closebut").onclick = close_popup;
	}

	if (container.style.display=="none") {
		show_hide('maximize_overlay');
		show_hide('maxedcontainer');
	}
	window.scrollTo(0,0);
	
	return false;
}

/**
 * Close the popup
 */
function close_popup()
{
	show_hide('maximize_overlay');
	show_hide('maxedcontainer');

	window.scrollTo(0,current_y);
}

function jump_to(link, limit, max_page)
{
	var no_page = document.getElementById("jump_to").value;
	if (!no_page || no_page <= 0 || no_page > max_page)
		return;
	var page = (no_page-1) * limit;
	link = link+'&page='+page;
	window.location.replace(link);
}

/**
 * Dislays or hides file exaples when pushing the "Display more examples..."/"Hide examples..." button
 * @param array elems. Array containing the ids for the elements (options from unordered list) which will be hidded or displayed
 * @param string button_id. Show/Hide button id
 */
function show_hide_file_examples(elems,button_id) {
	for (var i=0; i<elems.length; i++)
		show_hide(elems[i]);
	
	var button = document.getElementById(button_id);
	
	if (button.innerHTML == "Display more examples...")
		button.innerHTML =  "Hide examples...";
	else
		button.innerHTML = "Display more examples...";
}

/**
 * Cancel button action ( for Import files form within an iframe). Wil call the callback from pages.php and will remove the iframe keeping just it's content
 * @param string cb. Function to be called after cancel button is clicked
 * @param object cb_parameters, Example: {"equipment_id": 20,"equipment_type":"smsc"}
 * @param string cb_container_id. Id of the container holding the iframe.
 *				  If not provided falls to default "custom_step_0"
  */
function import_iframe_cancel_button (cb, cb_parameters, cb_container_id=null)
{
	var url = 'pages.php?method=' + cb;
	var json_cb_parameters = (cb_parameters) ? JSON.parse(JSON.stringify(cb_parameters)) : false;
	if(isObject(json_cb_parameters) && !emptyObj(json_cb_parameters)) {
		for (var field in json_cb_parameters) {
			url += "&" + field + "=" + json_cb_parameters[field];
		}
	}
	if (!cb_container_id)
		cb_container_id = 'custom_step_0';

	var callback = {
		"name": callback_request,
		"param": {
			"container_id": "import_form",
			"cbs": [
				{
				    "name": import_iframe_response,
				    "params": [cb_container_id]
				}
			]
		}
	};
	make_request(url, callback);
}

/**
 * Removes iframe used for import files by copying content from import_form (from iframe) in the container holding the iframe
 * @param string iframe_container_id The id of the container holding the iframe
 * @param string content_container_id The id of the container holding the content. If not provided falls to default id "import_form" 
 * @param string magic_reload The link to reload the main page after changes are done in the imported iframe form
 */
function import_iframe_response(iframe_container_id, content_container_id = null, magic_reload = null)
{
    if (!content_container_id)
	    content_container_id = 'import_form';
	if (magic_reload)
		window.top.location = magic_reload;
	else
		window.parent.document.getElementById(iframe_container_id).innerHTML = document.getElementById(content_container_id).innerHTML;
}

function isObject(obj)
{
	return(obj !== null && typeof obj === 'object');
}

function emptyObj(obj) 
{
    for(var key in obj) {
        if(obj.hasOwnProperty(key))
            return false;
    }
    return true;
}

function scrollToTop()
{
	document.body.scrollTop = 0; // For Safari
	document.documentElement.scrollTop = 0; // For Chrome, Firefox, IE and Opera
}

/**
 * Builds an HTML element
 * @param object elem. This parameter is used to set the attributes for the HTML element.
 * Example: elem = {"tag":"input","type":"text","class":"margintop","id":"test_input", "name":"test_input", "placeholder": "item1, item2"};
 * returns the HTML element created 
 */
function build_html_element(elem)
{
	//Check if elem is object
	if(!isObject(elem)) {
		console.log("Invalid parameter in argument.");
		return;
	}
	
	//Check if element tag was defined
	if (undefined === elem.tag || null === elem.tag) {
		console.log("Missing element tag.");
		return;
	}
	
	//Default element name
	var tag = elem.tag;
	
	//Define element attributes
	var general_attributes = ["class","id","name","style"];
	
	//Define attributes for each tag
	//TBI: select, textarea, etc
	var standard_attributes = {
		"input" : ["type","value","maxlength","disabled","checked","readonly","placeholder"]
	};
	
	//Check if element tag is implemented
	if (undefined === standard_attributes[tag] || null === standard_attributes[tag]) {
		console.log("Missing element implementation for tag '" + tag + "'");
		return;
	}
	
	//All attributes for a HTML element
	var all_attributes = general_attributes.concat(standard_attributes[tag]);
	
	//New HTML Element
	var new_elem = document.createElement(tag);
	
	//Set element attributes
	var atr;
	for (var i=0; i<all_attributes.length; i++) {
		atr = all_attributes[i];
		if (undefined !== elem[atr] && null !== elem[atr])
			new_elem.setAttribute(atr,elem[atr]);
	}
	
	return new_elem;
}

function display_custom_field(custom_value, table_id)
{
	var custom_id = "custom_" + table_id;
	var checkbox_id = table_id + "_custom_ck";
	var checkbox = (undefined !== document.getElementById(checkbox_id) && null !== document.getElementById(checkbox_id)) ? document.getElementById(checkbox_id) : null;
	if (checkbox === null) {
		console.log("No checkbox found.");
		return;
	}
	var custom_field = (undefined !== document.getElementById(custom_id) && null !== document.getElementById(custom_id)) ? document.getElementById(custom_id) : null;
	if (checkbox.checked) {
		//Check if HTML element exists
		if (custom_field === null) {
			var custom_elem = {"tag":"input","type":"text","class":"margintop","id":custom_id, "name":custom_id, "placeholder": "item1, item2, ..."};
			if (custom_value !== "" && custom_value !== null) {
				custom_elem["value"] = custom_value;
			}
			var parent_td = document.getElementById(checkbox_id).parentNode;
			
			//Create HTML element
			var custom_input = build_html_element(custom_elem);
			
			//Add element to parent
			parent_td.rowSpan = "2";
			parent_td.style = "vertical-align:top;";
			parent_td.appendChild(document.createElement("br"));
			parent_td.appendChild(custom_input);
		} else {
			//Display HTML element
			document.getElementById(custom_id).style.display = "initial";
		}
	} else if (custom_field!==null && custom_field.style.display!=="none") {
		//Hide HTML element
		document.getElementById(custom_id).style.display = "none";
	}
}

function display_password(toggle)
{
	//Toggle items
	var toggle_id = toggle.id;
	var toggle_class = toggle.className;
	var toggle_tag = toggle.tagName;
	
	//Get Input Password
	var password_id = toggle_id.replace("_toggle","");
	var input = document.getElementById(password_id);
	
	//Change input type
	input.type = (input.type==="password") ? "text" : "password";
	
	//Change the font-awsome element class
	if (toggle_tag.toLowerCase()==="span")
		toggle.className = (input.type==="password") ? toggle_class.replace("fa-eye","fa-eye-slash") : toggle_class.replace("fa-eye-slash","fa-eye");
}

function isJson(str) {
    try {
        JSON.parse(str);
    } catch (e) {
        return false;
    }
    return true;
}

// Check if a parameter is present (defined and not null)
function isPresent(val)
{
	return (undefined !== val && null !== val);
}
 
// Check if a parameter is missing (undefined or null)
function isMissing(val)
{
	return (undefined === val || null === val);
}
 
// Check if a parameter is filled (defined, not empty and not null)
function isFilled(val)
{
	return ("" != val && null !== val);
}
 
// Check if a parameter is empty (undefined, empty string or null)
function isEmpty(val)
{
	return ("" == val || null === val);
}

function is_substr(substring,string)
{
	return string.indexOf(substring) !== -1;
}

function is_array(arr)
{
	return (arr.constructor===Array);
}

function in_array(value, array)
{
	return array.indexOf(value) > -1;
}

function bool_value(value) 
{
	if (value==="t" || value==="1" || value===1 || value==="on" || value===true || value=== "true" )
		return true;
	return false;
}

// Check if an HTML element contains a specific class
// ex: hasClass(document.getElementById('field_id'), 'class_name')
function hasClass(element, className) 
{
	return (' ' + element.className + ' ').indexOf(' ' + className+ ' ') > -1;
}

//Function used to make the first letter uppercase
function ucfirst(string) 
{
	return string.charAt(0).toUpperCase() + string.slice(1);
}

/**
 * Displays element when pivot element's height exceeds the specified height.
 * @param {string} pivot_id The id of the pivot element which height is to be evaluated;
 * @param {string} elem_id The id of the element to be displayed if pivot height is greater than specified one.
 * @param {number} height Default value: 295; the height in pixels of the pivot element, after which the specified element will be displayed.
 */ 
function display_elem_on_height(pivot_id, elem_id, height = 295)
{
	if (isEmpty(pivot_id)) {
		console.log("display_elem_on_height: pivot_id argument is missing.");
		return;
	}
	
	if (isEmpty(elem_id)) {
		console.log("display_elem_on_height: elem_id argument is missing.");
		return;
	}
		
	if (!Number.isInteger(height)) {
		console.log("display_elem_on_height: height argument is not integer");
		return;
	}
	
	var pivot = document.getElementById(pivot_id);
	var elem = document.getElementById(elem_id);
	
	if (isEmpty(pivot)) {
		console.log("display_elem_on_height: No HTML element found with id'" + pivot_id + "'.");
		return;
	}
	
	if (isEmpty(elem)) {
		console.log("display_elem_on_height: No HTML element found with id'" + elem_id + "'.");
		return;
	}
	
	if (pivot.scrollHeight < height) {
		return;
	}
	
	elem.style.display = 'block';
}

function get_form_parameters(form_id, form_identifier = '', exceptions = [])
{
	if (isMissing(document.getElementById(form_id))) {
		console.log('Could not find form with form_id=' + form_id);
		return;
	}
	
	var form_elements = document.getElementById(form_id).elements;
	var form_parameters = {};
	
	var tags = ['input', 'textarea', 'select'];
	var tag_name;
	var json_field;
	for (var i=0; i<form_elements.length; i++) {
		if (exceptions.length > 0 && in_array(form_elements[i].id, exceptions))
			continue;
		tag_name = form_elements[i].tagName.toLowerCase();
		if (!in_array(tag_name, tags))
			continue;
		json_field = form_elements[i].id;
		if (form_identifier.length > 0 && json_field.substr(0, form_identifier.length) === form_identifier)
			json_field = json_field.substr(form_identifier.length);
		if(tag_name === "input") {
			switch(form_elements[i].type) {
				case "checkbox":
					form_parameters[json_field] = document.getElementById(form_elements[i].id).checked;
					break;
				default:
					form_parameters[json_field] = document.getElementById(form_elements[i].id).value;
			}
		} else
			form_parameters[json_field] = document.getElementById(form_elements[i].id).value;
	}
	
	return form_parameters;
}

function fill_form_parameters(form_id, parameters, form_identifier = '')
{
	if (isMissing(document.getElementById(form_id))) {
		console.log('Could not find form with form_id=' + form_id);
		return;
	}
	
	if (typeof parameters === "string")
		parameters = JSON.parse(parameters);
	
	if (form_identifier.length > 0) {
		var modified_parameters = {};
		for(var key in parameters)
			modified_parameters[form_identifier + key] = parameters[key];
		parameters = modified_parameters;
	}
	
	var form_elements = document.getElementById(form_id).elements;
	var tag_name;
	
	for (var i=0; i<form_elements.length; i++) {
		if (isMissing(parameters[form_elements[i].id]))
			continue;
		tag_name = form_elements[i].tagName.toLowerCase();
		if (tag_name === "input") {
			switch(form_elements[i].type) {
				case "checkbox":
					document.getElementById(form_elements[i].id).checked = bool_value(parameters[form_elements[i].id]);
					break;
				default:
					document.getElementById(form_elements[i].id).value = parameters[form_elements[i].id];
			}
		} else 
			document.getElementById(form_elements[i].id).value = parameters[form_elements[i].id];
	}
}

/**
 * Function used to expand to the left an iframe positioned to the right side of the screen.
 * @param {string} iframe_id The id of the iframe to be expanded.
 * @param {string} expand_icon_id The id of the expand to the left icon/image.
 */
function expand_frame(iframe_id = "docs_iframe", expand_icon_id = "docs_expand_icon") 
{
	var expand_class = document.getElementById(expand_icon_id).className;
	if (expand_class == "fa fa-angle-double-left") {
		document.getElementById(iframe_id).style = "position: absolute; width: 50%; margin-left: 47.5%; margin-top: 40px;";
		document.getElementById(expand_icon_id).className = "fa fa-angle-double-right";
		return;
	}
	document.getElementById(iframe_id).style = "";
	document.getElementById(expand_icon_id).className = "fa fa-angle-double-left";
}

function options_checkbox_group(elem_id)
{
	var elems = document.getElementsByName(elem_id+"[]");
	var options = [];
	for (var elem of elems)
		options.push(elem.value);
	
	return options;
}

function selectedIndex_checkbox_group(elem_id)
{
	var subelems = document.getElementsByName(elem_id+"[]");
	var options = [];
	for (var subelem of subelems) {
		if (subelem.checked)
			options.push(subelem.value);
	}
	
	return options;
}

function copy_dropdown_value(from_elem, to_elem_id)
{
	document.getElementById(to_elem_id).selectedIndex =from_elem.selectedIndex;
}
