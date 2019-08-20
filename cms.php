<?php
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>Surf Forecast Manager</title>
<script language="javascript" src="/lib/js/jquery/jquery-1.4.4.min.js"></script>
<script language="javascript">

function SFManager(){
	this.lastResult;
	this.lastRequest;
	this.lastError;
	this.results = {};
}

SFManager.prototype.apiSuccessHandler = function(request, result, status, xhr, callback){
	this.lastRequest = request;
	this.lastResult = result;
	this.results[requests] = result; 
	this.lastError = null;
	if(callback)callback.call(this, request, result, status, xhr);
}

SFManager.prototype.apiErrorHandler = function(request, result, status, xhr, callback){
	this.lastRequest = request;
	this.lastError = result;
	this.lastResult = null;
	alert("API Error on " + request + ": " + result.responseText);
	if(callback)callback.call(this, request, result, status, xhr);
}

SFManager.prototype.alert = function(msg){
	alert(msg);
}

SFManager.prototype.apiURL = function(request, params){
	var url = '/api/' + request;
	if(params){
		var qs = '';
		for(var p in params){
			qs += (qs ? '&' : '') + p + '=' + escape(params[p]);
		}
		url += '?' + qs;
	}
	return url;
}

SFManager.prototype.request = function(type, request, params, data, callback){
	var T = this;
	if(data)data = JSON.stringify(data);
	var settings = {
		type: type,
		data: data,
		success: function(result, status, xhr){ T.apiSuccessHandler(request, result, status, xhr, callback); }, 
		error: function(result, status, xhr){ T.apiErrorHandler(request, result, status, xhr, callback); }, 
		dataType: 'json'
	};

	settings.url = this.apiURL(request, params);
	//console.log(url);
	$.ajax(settings);
}

SFManager.prototype.get = function(request, params, callback){
	this.request('GET', request, params, null, callback);
}

SFManager.prototype.post = function(request, data, callback){
	this.request('POST', request, null, data, callback);
}

SFManager.prototype.put = function(request, data, callback){
	this.request('PUT', request, null, data, callback);
}

SFManager.prototype.delete = function(request, params, callback){
	this.request('DELETE', request, params, null, callback);
}

SFManager.prototype.getRows = function(request){
	var T = this
	this.get(request, null, this.updateDisplay);
}

SFManager.prototype.makeResultHeader = function(result){
	
}

SFManager.prototype.makeResultTableRow = function(row, ipts, fields, exclude, entityMap){
	var $tr = $('<tr/>');
	var $td;
	if(row.id){
		$td = $('<td/>');
		var $cb = $('<input type="checkbox" id="cb' + row.id + '"/>');
		$.data($cb[0], 'id', row.id);
		$td.append($cb);
		$td.append(" (" + row.id + ")");
		$tr.append($td);
		$tr.data('id', row.id);
		$tr.attr('id', 'row' + row.id);
	}

	ipts = ipts ? ipts.split(',') : [];
	var $ipts = {};
	for(var i = 0; i < ipts.length; i++){
		var p = ipts[i];
		if(entityMap && typeof entityMap[p] != 'undefined' && entityMap[p] != null){
			switch(entityMap[p].toLowerCase()){
				case 'textarea':
					$ipts[p] = $('<textarea class="input-' + p + '" name="' + p + '">' + (row[p] ? row[p] : '') + '</textarea>');
					break;
				default:
					throw new Error("Cannot determine entity " + entityMap[p]);
			}
		} else {
			$ipts[p] = $('<input type="text" value="' + (row[p] ? row[p] : '') + '" class="input-' + p + '" name="' + p + '"/>');
		}
		$.data($ipts[p][0], 'oldValue', row[p] == null ? '' : row[p]);
	}
	if(fields)fields = fields.split(',');
	
	for(var p in row){
		if(p == 'id')continue;
		if(fields){
			if(exclude && fields.indexOf(p) != -1)continue;
			if(!exclude && fields.indexOf(p) == -1)continue;
		}
			
		$td = $('<td/>');
		var append = true;
		if(typeof $ipts[p] == 'undefined'){
			if(row[p]){
				$td.html(row[p]);
			} else {
				$td.html(p);
				$td.addClass('whiteout');
			}
		} else {
			$td.append($ipts[p]);
		}
		$tr.append($td);
	}
	return $tr;
}

SFManager.prototype.makeNewRowRow = function(ipt, defVals, entityMap){
	var $tr = $('<tr/>');
	var $td = $('<td/>');
	var val = defVals && typeof defVals[ipt] != undefined ? defVals[ipt] : '';
	var $ipt;
	if(entityMap && typeof entityMap[ipt] != 'undefined' && entityMap[ipt] != null){
		switch(entityMap[ipt].toLowerCase()){
			case 'textarea':
				$ipt = $('<textarea class="input-' + ipt + '" name="' + ipt + '">' + val + '</textarea>');
				break;
			default:
				throw new Error("Cannot determine entity " + entityMap[ipt]);
		}
	} else { 
	 	$ipt = $('<input class="input-' + ipt + '" name="'+ ipt + '" value="' + val + '"/>');
	}
	$.data($ipt[0], 'oldValue', val == null ? '' : val);
	$td.html(ipt);
	$tr.append($td);
	$td = $('<td/>');
	$td.append($ipt);
	$tr.append($td);
	return $tr;
}

SFManager.prototype.makeHeaderRow = function(row, fields, exclude){
	var $tr = $('<tr/>');
	for(var p in row){
		if(fields && p != 'id'){
			if(exclude && fields.indexOf(p) != -1)continue;
			if(!exclude && fields.indexOf(p) == -1)continue;
		}
		
		var $td = $('<td/>');
		var s = p.replace('_', ' ');
		$td.html(s);
		$tr.append($td);
	}
	return $tr;
}

SFManager.prototype.alignRowWidths = function(){
	var $row = $('#rows-table').find('tr').first();
	var $header = $('#rows-header-table').find('tr').first();
	var $htds = $header.find('td');
	$row.find('td').each(function(idx, elt){
		$htds.eq(idx).width($(elt).width());
	});
}

SFManager.prototype.updateDisplay = function(request, result){
	var T = this;
	console.log("Update display: " + request);
	switch(request){
	case 'sources':
		var $table = $('#rows-table');
		$table.empty();
		var ipts = 'source,api_key,base_url,default_endpoint,default_querystring,active';
		$.each(result, function(idx, row){
			var $tr = T.makeResultTableRow(row, ipts);
			$table.append($tr);
		});

		$table = $('#new-row-table');
		$table.empty();
		ipts = ipts.split(',');
		$.each(ipts, function(idx, ipt){
			$table.append(T.makeNewRowRow(ipt));
		});

		$table = $('#rows-header-table');
		$table.empty();
		if(result.length > 0){
			$table.append(this.makeHeaderRow(result[0]));
			this.alignRowWidths();
		}
		break;

	case 'locations':
		var $table = $('#rows-table');
		$table.empty();
		var ipts = 'location,latitude,longitude,timezone,timezone_offset,forecast_location_id,active,description';
		$.each(result, function(idx, row){
			var $tr = T.makeResultTableRow(row, ipts, 'distance', true, {"description": 'textarea'});
			$table.append($tr);
		});
		
		$table = $('#new-row-table');
		$table.empty();
		ipts = ipts.split(',');
		$.each(ipts, function(idx, ipt){
			$table.append(T.makeNewRowRow(ipt, null, {"description": 'textarea'}));
		});

		$table = $('#rows-header-table');
		$table.empty();
		if(result.length > 0){
			$table.append(this.makeHeaderRow(result[0]));
			this.alignRowWidths();
		}
		break;

	case 'feeds':
		var $table = $('#rows-table');
		$table.empty();
		var ipts = 'endpoint,querystring';
		var fields = 'id,endpoint,querystring,location';
		$.each(result, function(idx, row){
			var $tr = T.makeResultTableRow(row, ipts, fields, false);
			$table.append($tr);
		});

		$table = $('#new-row-table');
		$table.empty();
		ipts = ipts.split(',');
		$.each(ipts, function(idx, ipt){
			$table.append(T.makeNewRowRow(ipt));
		});

		$table = $('#rows-header-table');
		$table.empty();
		if(result.length > 0){
			$table.append(this.makeHeaderRow(result[0], fields));
			this.alignRowWidths();
		}
		break;
	}
}

SFManager.prototype.isDirty = function(elt, prop, vals2save){
	var v = $.data(elt, prop);
	if(typeof v != 'undefined' && v != $(elt).val()){
		var name = $(elt).attr('name');
		vals2save[name] = $(elt).val();
		return true;
	}
	return false;
}

SFManager.prototype.saveRows = function(request){
	var vals2save = {};
	var dirty = 0;
	var T = this;
	$('#new-row-table').find(':input').each(function(idx, elt){
		if(T.isDirty(elt, 'oldValue', vals2save))dirty++;
	});	
	var row2insert = null;
	if(dirty > 0)row2insert = vals2save;
	var rows2update = [];
	

	var $table = $('#rows-table');
	$table.find('tr').each(function(idx, elt){
		vals2save = {};
		var n = dirty;
		$(elt).find(':input').each(function(iidx, ielt){
			if(T.isDirty(ielt, 'oldValue', vals2save))dirty++;
		});
		if(dirty > n){
			vals2save.id = $(elt).data('id');
			rows2update[rows2update.length] = vals2save;	
		}
	});
	if(row2insert){
		this.post(request, row2insert, this.savedRows);
	}
	var T = this;
	$.each(rows2update, function(idx, row){
		var req = request + '/' + row.id;
		T.put(req, row, T.savedRows);
	});
}

SFManager.prototype.savedRows = function(request, result){
	switch(request){
	default:
		$r = $('#row' + result.id);
		if($r.length){
			var T = this;
		 	$r.find(':input').each(function(idx, elt){
		 		var val = $(elt).val();
			 	if(T.isDirty(elt, 'oldValue', val))$.data(elt, 'oldValue', val);
			});
		} else {
			this.getRows(request.split('/')[0]);
		}
		break;
	}
}

SFManager.prototype.deleteRows = function(request){
	var T = this;
	var $toDelete = $('#rows-table input:checked'); 
	if($toDelete.length){
		if(!confirm('Are you sure you want to delete ' + $toDelete.length + " rows"))return;
	}
	$toDelete.each(function(idx, elt){
		var id = $.data(elt, 'id');
		if(typeof id != 'undefined' && id){
			var req = request + "/" + id;
			T.delete(req, null, T.deletedRows);
		}
	});
}

SFManager.prototype.deletedRows = function(request, result){
	switch(request){
	default:
		this.getRows(request.split('/')[0]);
		break;
	}
}



var app = new SFManager();
SFManager.prototype.test = function(s){
	console.log(this);
	console.log(s);
}

$(document).ready(function(){
	//attach functions to elemetns
	app.get('sources');
	app.get('locations');
	
	$('#requests').change(function(event){
		var req = $(this).val();
		if(req){
			app.getRows(req);
		} else {
			//empty
		}
	});

	$('#save-rows').click(function(event){
		var req = $('#requests').val();
		if(req)app.saveRows(req);
	});

	$('#refresh-rows').click(function(event){
		var req = $('#requests').val();
		if(req)app.getRows(req);
	});
	
	$('#delete-rows').click(function(event){
		var req = $('#requests').val();
		if(req)app.deleteRows(req);
	});
});

</script>

<style>
body{
	font-family: helvetica, arial;
	font-size: 12px;
}
#rows table, #rows input{
	font-family: helvetica, arial;
	font-size: 12px;
}
#rows-header td{
	font-weight: bold;
}
.whiteout{
	color: white;
}
.input-api_key, .input-base_url, .input-default_endpoint{
	width: 200px;
}

.input-default_querystring, .input-querystring, .input-default_payload, .input-payload{
	width: 350px;
}
	
.input-active{
	width: 30px;
}
</style>

</head>


<body>


<div id="actions">
	View: 
	<select id="requests">
		<option value="">-- Select --</option>
		<option value="sources">Sources</option>
		<option value="locations">Locations</option>
		<option value="feeds">Feeds</option>
		<option value="test">Test</option>
	</select>
	<input type="button" id="save-rows" value="Save Rows"/>
	<input type="button" id="reset-rows" value="Reset Rows"/>
	<input type="button" id="refresh-rows" value="Referesh Rows"/>
	<input type="button" id="delete-rows" value="Delete Rows"/>
</div>

<div id="new-row">
	<table id="new-row-table">
	</table>
</div>
<div id="rows-header">
	<table id="rows-header-table">
	</table>
</div>
<div id="rows">
	<table id="rows-table">
	</table>
</div>

</body>
</html>

