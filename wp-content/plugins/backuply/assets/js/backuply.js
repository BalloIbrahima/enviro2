/*
* BACKUPLY
* https://backuply.com
* (c) Backuply Team
*/


jQuery(document).ready(function(){
	
	backuply_handle_tab();
	
	backuply_handle_custom_cron();
	
	// To Copy text on click
	jQuery('.backuply-code-copy').click( function() {
		navigator.clipboard.writeText(jQuery(this).parent().find('.backuply-code-text').text());
		jQuery(this).parent().find('.backuply-code-copied').show();
		
		setTimeout(function() {
			jQuery('.backuply-code-copied').hide();
		},1500);
	});
	
	jQuery('[name="backuply_create_backup"]').click(function(e) {
		backuply_create_backup(jQuery(this), e);
	});
	
	jQuery('[name="backuply_stop_backup"], .backuply-stop-backup').click(function(e) {
		backuply_stop_backup(jQuery(this), e);
	});
	
	jQuery('#backuply-kill-process-btn').click(function(e) {
		e.preventDefault();
		let confirmation = confirm('Are you sure you want to kill this process ?');
		
		if(!confirmation){
			return;
		}
		
		backuply_kill_process(jQuery(this));
	});
	
	checkprotocol();
	jQuery('#protocol').change(function(){
		checkprotocol();
	});
	
	backuply_cron_backup_style(backuply_obj.cron_task);

	jQuery("#check_all_edit").on("click", function(event){
		if(this.checked == true){
			jQuery(".soft_filelist").prop("checked", true);
		}else{
			jQuery(".soft_filelist").prop("checked", false);
		}
	});
	
	jQuery('#backuply-htaccess-fix').click(function() {
		event.preventDefault();
		jQuery('#backuply-htaccess-modal').show();
	});
	
	jQuery('#backuply_retry_htaccess').click(function() {
		event.preventDefault();
		
		backuply_retry_htaccess(jQuery(this));
	});
	
	jQuery('.backuply-download-backup').click(function(){
		event.preventDefault();
		
		backuply_download_backup(jQuery(this));
	});
	
	// For Select all button on History tab
	jQuery('#backuply-bak-select-all').click(function() {
		event.preventDefault();
		const checkboxes = jQuery('[name="backuply_selected_bak[]"]');
		
		checkboxes.prop('checked', !checkboxes.prop('checked'));
	});
	
	// For Select all button delete
	jQuery('#backuply-bak-multi-delete').click(function() {
		event.preventDefault();
		backuply_bak_multi_delete(jQuery(this));
	});
	
	if(jQuery('#restore_message').length && jQuery('#restore_message').css('display') != 'none'){
		backuply_update_modal_str(true);
		setTimeout(backuply_backup_progress, 2000);
	}
	
	// Brings up Backup/Restore Status Modal
	jQuery('.backuply-check-status').click(function() {
		let is_restoring = false;
		
		if(jQuery(this).closest('#message').parent().prop('id') == 'restore_message'){
			is_restoring = true;
		}
		
		backuply_update_modal_str(is_restoring);

		jQuery('.backuply-modal').show();
		backuply_backup_progress();
	});
	
	
	// Last log for backup
	jQuery('.backuply-backup-last-log').click(function(){
		
		let file_name = jQuery(this).data('file-name'),
		proto_id = jQuery(this).closest('tr').data('proto-id'),
		log_name = 'Last Backup Log';
		
		if(file_name){
			log_name = 'Logs for ' + file_name.replace('_log.php', '');
		}

		jQuery('#backuply-backup-last-log').dialog({
			autoOpen: true,
			draggable: false,
			height: 600,
			width: 600,
			modal: true,
			title : log_name
		});

		backuply_load_last_logs(false, file_name, proto_id);
	});
	
	// Last Log for restore
	jQuery('.backuply-restore-last-log').click(function(){
		jQuery('#backuply-restore-last-log').dialog({
			autoOpen: true,
			draggable: false,
			height: 600,
			width: 600,
			modal: true,
			title : 'Last Restore Log'
		});
		
		backuply_load_last_logs(true);
	});
	
	
	let messages = jQuery('#restore_message, #process_message');

	messages.each((_, ele) => {
		if(jQuery(ele).css('display') == 'none') {
			return;
		}
		
		if(jQuery(ele).prop('id') == 'restore_message') {
			backuply_progress_init(true);
			return;
		}
	
		backuply_progress_init();
	});
	
	jQuery(function () {
		let form = jQuery('#add_backup_loc_form form'),
			submit_btn = form.find('button');
	
		form.find('[name="addbackuploc"]').val('true');
		
		jQuery('#add_backup_loc_form').dialog({
			autoOpen: false
		});

		// Add Backup Location listener
		jQuery('#add_backup_loc_btn').click(function() {
			form[0].reset();
			
			form.find('[name="protocol"]').prop('disabled', false);
			submit_btn.text('Add Backup Location');
			backuply_update_labels('ftp');
			checkprotocol();
			
			jQuery('#add_backup_loc_form').dialog({
				autoOpen: true,
				draggable: false,
				height: 600,
				width: 500,
				modal: true,
				title : 'Add Backup Location'
			});
		});
		
		// Edit Backup Listener
		jQuery('[name="backuply_edit_loc"]').click(function() {
			event.preventDefault();
			
			let loc_id = jQuery(this).closest('td').find('[name="edit_loc_id"]').val();

			form[0].reset();
			
			jQuery('#add_backup_loc_form').dialog({
				autoOpen: true,
				draggable: false,
				height: 600,
				width: 500,
				modal: true,
				title : 'Edit Backup Location'
			});

			// Edit Backup Location
			jQuery.ajax({
				url : backuply_obj.ajax_url,
				method : 'POST',
				data : {
					security : backuply_obj.nonce,
					loc_id : loc_id,
					action : 'backuply_get_loc_details'
				},
				success : function(res){
					if(res.message){
						alert(message);
						return;
					}
					
					form.find('[name="backuply_edit_location"]').val('true');
					form.find('[name="addbackuploc"]').val('');
					submit_btn.text('Edit Backup Location');
					
					let data = res.data;
						
					let inputs = {
						'location_name' : data.name ?  data.name : '',
						'protocol' : data.protocol ?  data.protocol : '',
						'aws_endpoint' : data.aws_endpoint ?  data.aws_endpoint : '',
						'aws_region' : data.aws_region ?  data.aws_region : '',
						'aws_accessKey' : data.aws_accessKey ?  data.aws_accessKey : '',
						'aws_secretKey' : data.aws_secretKey ?  data.aws_secretKey : '',
						'aws_bucketname' : data.aws_bucketname ?  data.aws_bucketname : '',
						's3_compatible' : data.s3_compatible ? data.s3_compatible : '',
						'aws_sse' : data.aws_sse ? data.aws_sse : '',
						'access_code' : data.access_code ?  data.access_code : '',
						'server_host' : data.server_host ?  data.server_host : '',
						'port' : data.port ?  data.port : '',
						'ftp_user' : data.ftp_user ?  data.ftp_user : '',
						'ftp_pass' : data.ftp_pass ?  data.ftp_pass : '',
						'backup_loc' : data.backup_loc ?  data.backup_loc : '',
						'edit_loc_id' : data.id ?  data.id : ''
					}
					
					for(const i in inputs){
						if(i == 'protocol') {
							form.find('[name="'+i+'"]').prop('disabled', true);
							
							if(inputs[i] in ['ftp', 'ftps', 'sftp', 'webdav']){
								backuply_update_labels(inputs[i]);
							}
						}
						
						form.find('[name="'+i+'"]').val(inputs[i]);
						
						if(inputs[i] && i == 'aws_sse' && inputs['protocol'] == 'aws'){
							form.find('[name="'+i+'"]').prop('checked', 'checked');
						}
						
					}
					
					jQuery('#edit-protocol').change(() => {
						checkprotocol();
					});
					checkprotocol();
				}
			});
		});
	});

	if(jQuery('.error').length > 0 && window.location.hash == '#backuply-location') {
		jQuery('#add_backup_loc_form').dialog({
			autoOpen: true,
			draggable: false,
			height: 600,
			width: 500,
			modal: true
		});
	}
	
	jQuery('#backuply-btn-sync-bak').click(function() {
		event.preventDefault();
		backuply_sync_backup(jQuery(this));
	});
	
	
	jQuery('#backuply-exclude-file-pattern').click(function() {
		jQuery('#backuply-exclude-pattern').dialog({
			autoOpen: true,
			draggable: false,
			height: 500,
			width: 490,
			modal: true,
			title : 'Exclude Pattern'
		});

		backuply_handle_exclude_pattern(jQuery('#backuply-exclude-pattern'));
	});

	jQuery('#backuply-exclude-file-specific').click(function() {
		jQuery('#backuply-exclude-specific').dialog({
			autoOpen: true,
			draggable: false,
			height: 600,
			width: 500,
			modal: true,
			title : 'Exclude Specific File/Folder'
		});
		
		backuply_exclude_specific(jQuery('#backuply-exclude-specific'));
	});
	
	jQuery('.backuply-pattern-delete').on('click', backuply_delete_exclude_rule);
	
	jQuery('.backuply-js-tree').jstree({
		'core' : {
			'multiple' : false,
			'data' : function(node, cb){
				jQuery.ajax({
					method : 'POST',
					url : backuply_obj.ajax_url,
					data : {
						action : 'backuply_get_jstree',
						security : backuply_obj.nonce,
						nodeid : node,
					},
					success : function(res){
						cb.call(this, res.nodes);
					}
				})
			},
			
		}
	});
});	

// Handles Dashboard Tabs
function backuply_handle_tab() {

	var backuply_tabs = jQuery('.nav-tab-wrapper'),
	backuply_tab;
	
	if(!window.location.hash || jQuery(window.location.hash).length == 0) {
		jQuery('.backuply-tab').hide();
		jQuery('#backuply-dashboard').show();
		backuply_tab = backuply_tabs.find('[href="#backuply-dashboard"]');
	} else {
		jQuery('.backuply-tab').hide();
		jQuery(window.location.hash).show();
		backuply_tab = backuply_tabs.find('[href="'+window.location.hash+'"]');
	}
	
	backuply_tab.addClass('nav-tab-active');
	
	jQuery('.backuply-tab-wrapper .nav-tab').click(function() {
		event.preventDefault();
		history.replaceState({}, '', event.target.href);
		jQuery('.backuply-tab').hide();
		jQuery(window.location.hash).show();
		
		backuply_tab = backuply_tabs.find('[href="'+window.location.hash+'"]');
		backuply_tabs.find('.nav-tab-active').removeClass('nav-tab-active');
		backuply_tab.addClass('nav-tab-active');	
	});
}

function backuply_kill_process(jEle) {
	
	jEle.siblings('button').addClass('backuply-disabled').off('click');
	
	jQuery.ajax({
		method : 'GET',
		url : backuply_obj.ajax_url + '?action=backuply_kill_proccess&security=' + backuply_obj.nonce,
		success : function(res) {
			if(!res.success) {
				alert('Something went wrong unable to kill process');
				return;
			}
			
			alert('The process was killed successfully, Now we will reload the page');
			location.reload();
		}
	});
}

function backuply_update_modal_str(is_restore = false) {
	let modal = jQuery('#backuply-backup-progress');
	
	if(is_restore) {
		modal.find('.backuply-title-restore').show();
		modal.find('.backuply-title-backup').hide();
		modal.find('.backuply-progress-extra-restore').show();
		modal.find('.backuply-progress-extra-backup').hide();
		modal.find('.backuply-loc-restore-name').show();
		modal.find('.backuply-loc-bak-name').hide();
		modal.find('.backuply-stop-backup').hide();
		
		return;
	}
	
	//When we are backing-up
	modal.find('.backuply-title-restore').hide();
	modal.find('.backuply-title-backup').show();
	modal.find('.backuply-progress-extra-restore').hide();
	modal.find('.backuply-progress-extra-backup').show();
	modal.find('.backuply-loc-bak-name').show();
	modal.find('.backuply-loc-restore-name').hide();
}

// Deletes multiple selected backups
function backuply_bak_multi_delete(jEle) {
	event.preventDefault();
	
	const sel_bak = jQuery('[name="backuply_selected_bak[]"]:checked'),
		tab = jEle.closest('#backuply-history'),
		spinner = tab.find('.spinner');
		
	
	if(sel_bak.length < 1) {
		alert('No Backup Selected For Deletion');
		return;
	}
	
	const do_delete = confirm('You are about to delete ' + (sel_bak.length) + ' backup(s)\n\n Are you sure you want to delete?');

	if(!do_delete) {
		return;
	}
	
	sel_bak.each((_, bak) => {
		let bak_name = jQuery(bak).val(),
			row = tab.find('[value="'+ bak_name +'"]').closest('tr');
		
		//AJAX Call
		jQuery.ajax({
			method : 'POST',
			url : backuply_obj.ajax_url,
			data : {
				action : 'backuply_multi_backup_delete',
				security : backuply_obj.nonce,
				backup_name : bak_name
			},
			beforeSend : function() {
				spinner.addClass('is-active');
				row.css('background-color', '#f2c2c2');
			},
			success : function(res) {
				if(!res.success) {
					alert(res.message ? res.message : 'Something went wrong!');
					spinner.removeClass('is-active');
					return;
				}
				
				row.remove();
				spinner.removeClass('is-active');
			}
		});
	});
}

// Updates Custom Cron on Input
function backuply_handle_custom_cron() {
	var custom_cron = jQuery('#backuply-custom-cron');
	
	custom_cron.find('[name="cron_minute"]').on('input', function() {
		var star = custom_cron.find('#backuply-cron-min'),
			input_val = jQuery(this).val();
		
		if(!input_val) {
			star.text('*');
			return;
		}
		
		star.text(input_val);
	});
	
	custom_cron.find('[name="cron_hour"]').on('input', function() {
		var star = custom_cron.find('#backuply-cron-hour'),
			input_val = jQuery(this).val();
		
		if(!input_val) {
			star.text('*');
			return;
		}
		
		star.text(input_val);
	});
	
	custom_cron.find('[name="cron_day"]').on('input', function() {
		var star = custom_cron.find('#backuply-cron-day'),
			input_val = jQuery(this).val();
		
		if(!input_val) {
			star.text('*');
			return;
		}
		
		star.text(input_val);
	});
	
	custom_cron.find('[name="cron_month"]').on('input', function() {
		var star = custom_cron.find('#backuply-cron-month'),
			input_val = jQuery(this).val();
		
		if(!input_val) {
			star.text('*');
			return;
		}
		
		star.text(input_val);
	});
	
	custom_cron.find('[name="cron_weekday"]').on('input', function() {
		var star = custom_cron.find('#backuply-cron-weekday'),
			input_val = jQuery(this).val();
		
		if(!input_val) {
			star.text('*');
			return;
		}
		
		star.text(input_val);
	});
}

// AJAX Call to retry creation of htaccess
function backuply_retry_htaccess(jEle) {
	
	jQuery.ajax({
		method : 'GET',
		url : backuply_obj.ajax_url,
		data : {
			'action' : 'backuply_retry_htaccess',
			'security' : backuply_obj.nonce
		},
		beforeSend : function() {
			jEle.empty();
			jEle.append('Trying...');
		},
		success : function(res) {
			jEle.empty().text('Click Here');
			
			if(!res.success) {
				if(res.message) {
					alert(res.message);
				} else {
					alert('We are unable to create the htaccess file');
				}
			}
			
			setTimeout(() => {
				location.reload();
			}, 2000);
			
			return;
		}
	});
}

// Event handler for Stop backup
function backuply_stop_backup(jEle) {
	event.preventDefault();
	
	jQuery.ajax({
		method : 'GET',
		url  : backuply_obj.ajax_url,
		data : {
			action : 'backuply_stop_backup',
			security : backuply_obj.nonce
		},
		success : function(res) {
			if(!res.success) {
				alert('Unable to stop the backup');
				return;
			}
			
			jEle.text('Stopping...').prop('disabled', true).off('click');
		}
	});
}

// Event handler for create backup
function backuply_create_backup(jEle) {
	event.preventDefault();
	
	var form = jEle.closest('form'),
		values = form.serializeArray();
		
	var new_obj = {};
	
	for(let val of values) {
		new_obj[val['name']] = val['value'];
	}
	
	if(!new_obj['backup_db'] && !new_obj['backup_dir']) {
		alert('Please select an option to backup');
		return;
	}
	
	backuply_update_modal_str();
	
	let modal = jQuery('#backuply-backup-progress'),
		image = modal.find('.backuply-modal-bak-icon'),
		protocol = jEle.closest('form').find('select option:selected').data('protocol'),
		proto_name = jEle.closest('form').find('select option:selected').text(),
		bak_name = modal.find('.backuply-loc-bak-name');

	proto_name = proto_name.match(/^(.+?)(?=\()/);
	bak_name.text('Backing Up To: ' + proto_name[0]);
	image.prop('src', backuply_obj.images + (protocol ? protocol : 'local') + '.svg');
	
	modal.find('.backuply-backup-status').empty();
	modal.find('.backuply-progress-value').css('width', '0%');
	modal.find('.dashicons-no').show();
	modal.data('process', 'backup');

	modal.show();
	
	backuply_progress_init();

	jQuery.ajax({
		method : 'POST',
		url : backuply_obj.ajax_url,
		data : {
			action : 'backuply_create_backup',
			security : backuply_obj.nonce,
			values : JSON.stringify(new_obj)
		},
		success : function(res){
			if(!res.success) {
				alert(res.message ? res.message : 'Something went Wrong!, Try again later');
				return;
			}
			
			backuply_backup_progress();
		}
	})
}

let backuply_status = {
	has_ended : false,
	fail_count : 0,
	has_ended : false
};

// Sets up Progress Modal
function backuply_progress_init(is_restore = false, title = 'Backup') {
	let modal = jQuery('#backuply-backup-progress');
	
	modal.on('click', '.dashicons-no', function() {
		modal.hide();
	});
	modal.find('.backuply-backup-status').empty();
	modal.find('.backuply-progress-value').css('width', '0%').text('0%');
	jQuery('[name="backuply_create_backup"]').addClass('backuply-disabled');
	jQuery('[name="backuply_stop_backup"]').prop('disabled', false);
	
	if(is_restore) {
		modal.find('.backuply-title-restore').show();
		modal.find('.backuply-title-backup').hide();
		modal.find('.backuply-progress-extra-text').text('We are restoring your files it may take some time.');
	} else {
		modal.find('.backuply-title-restore').hide();
		modal.find('.backuply-title-backup').show();
	}
	
	if(!is_restore) {
		jQuery('#process_message').show();
	}
	
	backuply_status.is_restore = is_restore;
	
	// let interval = setInterval(() => {
		// if(backup_status.has_ended) {
			// clearInterval(interval);
			// return;
		// }
		
		// backuply_backup_progress();
	// }, 2000);
}

// Toggles additional file option
function toggle_advoptions(ele){
	
	let target = jQuery('#' + ele);
	
	if (target.is(':hidden')){
		target.slideDown('slow');
		target.prev().attr('class', 'dashicons-before dashicons-minus');
	}
	else{
		target.slideUp('slow');
		target.prev().attr('class', 'dashicons-before dashicons-plus-alt');
	}
}

function conf_del(conf_msg){
	return confirm(conf_msg);
}

function checkprotocol(is_edit = false){
	
	let edit_str = is_edit ? '#edit-' : '#',
		protocol_id = edit_str + 'protocol',
		port_id = edit_str + 'port';
	
	if(jQuery(protocol_id).val() == 'ftp' || jQuery(protocol_id).val() == 'softftpes'){
		//alert('insode ftp');
		if(jQuery(port_id).val() == '' || jQuery(port_id).val() == '22' || jQuery(port_id).val() == '443'){
			jQuery(port_id).val('21');
		}
		
		if(jQuery(protocol_id).val() == 'ftp') {
			backuply_update_labels('ftp');
		} else {
			backuply_update_labels('ftps');
		}
		
		show_ftp();
		hide_aws_s3bucket();
		hide_dropbox();
	}

	if(jQuery(protocol_id).val() == 'softsftp'){
		backuply_update_labels('sftp');
		update_sftp_loc_desc(true);

		if(jQuery(port_id).val() == '' || jQuery(port_id).val() == '21' || jQuery(port_id).val() == '443'){
			jQuery(port_id).val('22');
		}
		
		show_ftp();
		hide_aws_s3bucket();
		hide_dropbox();
	} else {
		update_sftp_loc_desc();
	}

	if(jQuery(protocol_id).val() == 'webdav'){

		backuply_update_labels('webdav');
		
		if(jQuery(port_id).val() == '' || jQuery(port_id).val() == '21'){
			jQuery(port_id).val('443');
		}
		
		show_ftp();
		hide_aws_s3bucket();
		hide_dropbox();
	}

	if(jQuery(protocol_id).val() == 'gdrive' || jQuery(protocol_id).val() == 'onedrive'){
		//alert('hide ftp');
		hide_ftp();
		hide_aws_s3bucket();
		hide_dropbox();
	}

	if(jQuery(protocol_id).val() == 'aws' || jQuery(protocol_id).val() == 'caws'){
		//alert('hide ftp');
		hide_ftp();
		show_aws_s3bucket();
		hide_dropbox();
		
    // Toggles S3 Compatible Options
		if(jQuery(protocol_id).val() == 'caws'){
			jQuery('.bakuply-s3-compatible').show();
			jQuery('.aws_sse').hide();
		} else {
			jQuery('.bakuply-s3-compatible').hide();
			jQuery('.aws_sse').show();
		}
		
	}

	if(jQuery(protocol_id).val() == 'dropbox'){
		//alert('hide ftp');
		hide_ftp();
		hide_aws_s3bucket();
		show_dropbox();
	}
}

function backuply_update_labels(protocol = 'ftp') {
	let s_host_desc = jQuery('[name="server_host"]').parent().find('.backuply-opt-label__helper'),
		port_desc = jQuery('[name="port"]').parent().find('.backuply-opt-label__helper'),
		uname_label = jQuery('[name="ftp_user"]').parent().find('.backuply-opt-label__title'),
		uname_desc = jQuery('[name="ftp_user"]').parent().find('.backuply-opt-label__helper'),
		pass_label = jQuery('[name="ftp_pass"]').parent().find('.backuply-opt-label__title'),
		pass_desc = jQuery('[name="ftp_pass"]').parent().find('.backuply-opt-label__helper');

	let fileds = {
		'ftp' : {
			's_host' : ['Enter the server host e.g. ftp.mydomain.com'],
			'port' : ['Enter the port to connect (default FTP port is 21)'],
			'u_name' : ['The Username of your FTP Account', 'FTP Username'],
			'pass' : ['The Password of your FTP account', 'FTP Password']
		},
		'ftps' : {
			's_host' : ['Enter the server host e.g. ftps.mydomain.com'],
			'port' : ['Enter the port to connect (default FTPS port is 21)'],
			'u_name' : ['The Username of your FTPS Account', 'FTPS Username'],
			'pass' : ['The Password of your FTPS account', 'FTPS Password']
		},
		'sftp' : {
			's_host' : ['Enter the server host e.g. sftp.mydomain.com'],
			'port' : ['Enter the port to connect (default SFTP port is 22)'],
			'u_name' : ['The Username of your SFTP Account', 'SFTP Username'],
			'pass' : ['The Password of your SFTP account', 'SFTP Password']
		},
		'webdav' : {
			's_host' : ['Enter the server host e.g. webdav.mydomain.com'],
			'port' : ['Enter the port to connect (default WebDAV port is 443)'],
			'u_name' : ['The Username of your WebDAV Account', 'WebDAV Username'],
			'pass' : ['The Password of your WebDAV account', 'WebDAV Password']
		}
	}
	
	s_host_desc.text(fileds[protocol]['s_host'][0]);
	port_desc.text(fileds[protocol]['port'][0]);
	uname_label.text(fileds[protocol]['u_name'][1]);
	uname_desc.text(fileds[protocol]['u_name'][0]);
	pass_label.text(fileds[protocol]['pass'][1]);
	pass_desc.text(fileds[protocol]['pass'][0]);
}

function show_ftp(){
	jQuery('[name="backup_loc"]').parent().find('.backuply-opt-label__title').text('Backup Location (Required)');
	jQuery('.ftp_details').show();
	jQuery('.ftp_credentials').show();
}

function hide_ftp(){
	jQuery('[name="backup_loc"]').parent().find('.backuply-opt-label__title').text('Backup Location (Optional)');
	jQuery('.ftp_details').hide();
	jQuery('.ftp_credentials').hide();
}

// Updates the description of SFTP as it requires ABSOLUTE PATH
function update_sftp_loc_desc(is_sftp = false) {
	let desc_holder = jQuery('[name="backup_loc"]').parent().find('.backuply-opt-label__helper');
	
	if(is_sftp) {
		desc_holder.text('Absolute path to backup directory e.g. /home/USERNAME/backups');
		return;
	}
	
	desc_holder.text('Backup Directory e.g. /backups or you can leave empty to allow Backuply to manage the');
}

function show_aws_s3bucket(){
	jQuery('.aws_s3bucket').show();
}

function hide_aws_s3bucket(){
	jQuery('.aws_s3bucket').hide();
}

function show_dropbox(){
	jQuery('.dropbox_authorize').show();
}

function hide_dropbox(){
	jQuery('.dropbox_authorize').hide();
}

//TOOD:: Not required
if(window.location.href.indexOf('&addbackuploc') > -1){
	var reload_url = window.location.href;
	
	// Truncate unnecessary part of URL
	reload_url_arr = reload_url.split('&addbackuploc=');

	// Try to update url without reloading the window
	if (typeof (history.pushState) != 'undefined') {
		history.pushState({}, null, reload_url_arr[0]);
	} else {
		if(window.location.href != reload_url_arr[0]){
			window.location.href = reload_url_arr[0];
		}
	}
}

// Disabling Buttons when restoring or backingup
function backuply_backup_style(style_for){
	var input_button = jQuery('.button');
	var other_inputs = jQuery('.disable');
	input_button.prop('disabled', true);
	input_button.val('Backup is in process');

	switch(style_for){
		case 1:
			var backup_message = jQuery('#process_message');
			backup_message.show();
			input_button.val('Backingup...');
			break;
		case 2:
			var backup_message = jQuery('#restore_message');
			backup_message.show();
			input_button.val('Restoring...');
			break;
	}
	
	other_inputs.prop('disabled', true);
}

// Updates the Backup status in the Modal
function backuply_backup_progress() {
	const progress = jQuery('.backuply-progress-value'),
	modal = progress.closest('.backuply-modal'),
	status_box = jQuery('.backuply-backup-status'),
	stop_modal = jQuery('.backuply-stop-backup');
	
	let ajax_url = backuply_obj.ajax_url + '?action=backuply_check_backup_status';
	
	if(backuply_status.hasOwnProperty('is_restore') && backuply_status.is_restore){
		if(!backuply_status.progress){
			backuply_status.progress = 0;
		}
		
		ajax_url = backuply_obj.backuply_url + '/status_logs.php?backuply_key=' + backuply_obj.backuply_key;
	}

	if(!backuply_status.hasOwnProperty('is_restore')) {
		const stop = progress.closest('.backuply-settings-block').find('[name="backuply_stop_backup"]');
		stop_modal.show();
	}
	
	let ajax_data = {};
	
	backuply_status.last_status = backuply_status.last_status ? backuply_status.last_status : 0;
	
	ajax_data['last_status'] = backuply_status.last_status;
	ajax_data['security'] = backuply_obj.nonce;
	
	if(backuply_status.hasOwnProperty('is_restore')) {
		ajax_data['is_restore'] = backuply_status['is_restore'];
		ajax_data['backup_name'] = backuply_status['backup_name'];
	}

	jQuery.ajax({
		method : 'POST',
		url : ajax_url,
		data : ajax_data,
		success : function(res) {
			if(!res.success) {
				backuply_status.fail_count++;
				
				if(backuply_status.fail_count > 15) {
					alert(res.message ? res.message : 'Something went wrong!');
					backuply_status.has_ended = true;
					return;
				}
			}
			
			if(!res.progress_log) {
				setTimeout(backuply_backup_progress, 2000);
				return;
			}
			
			backuply_status.last_status += res.progress_log.length;

			res.progress_log = res.progress_log.split('\n');
			
			for(let text of res.progress_log) {
				if(!text){
					continue;
				}
				
				if(!text.includes('|')) {
					continue;
				}
				
				let [log, status, percent] = text.split('|'),
					color = '';
				percent = parseInt(percent);
				
				switch(status) {
					case 'info':
						color = 'yellow';
						break;
					
					case 'error': 
						color = 'red';
						break;
						
					case 'success':
						color = 'rgb(102, 187, 106)';
						break;
				}
				
				backuply_status.progress = parseInt(backuply_status.progress);
				
				if(backuply_status.progress && backuply_status.progress > percent) {
					percent = backuply_status.progress;
				}
				
				// Updating Progress Bar
				if(percent < 0 ) {
					progress.css('width', '100%').text('Stopping...');
					
					if(!backuply_status.hasOwnProperty('is_restore')) {
						stop.off('click').text('Stopping...').prop('disabled', true);
					}
				} else if(percent != 0 && backuply_status.progress != percent){
					
					setTimeout(() => {
						progress.css('width', percent + '%').text(percent + '%');
					}, 300); 
				}
				
				if(percent == 100 && stop_modal != 'undefined') {
					stop_modal.off('click').prop('disabled', true).addClass('backuply-disabled');
				}
				
				if(backuply_status.progress != percent){
					backuply_status.progress = percent;
				}
				
				if(status == 'uploading') {
					if(status_box.find('.backuply-upload-progress')) {
						status_box.find('.backuply-upload-progress').remove();
					}
					status_box.append(log);
				} else {
					let html = '<p'+ (color ? ' style="color:'+color+'"' : '')+ '>';
					
					if(status == 'success') {
						html += '<span class="dashicons dashicons-saved"></span>';
					}
					
					html += log;
					html += '</p>';
					
					status_box.append(html);
				}
				
				status_box.scrollTop(status_box[0].scrollHeight); //Keeps the Scroll at bottom
				
				// Toggeling State of buttons on success and error
				if(status == 'success' || status == 'error' || res.progress == '100') {
					let finish_btn = modal.find('.backuply-backup-finish');
					finish_btn.prop('disabled', false).removeClass('backuply-disabled');
					
					if(!backuply_status.hasOwnProperty('is_restore')) {
						stop.off('click').prop('disabled', false).addClass('backuply-disabled');
						stop.parent().prev().find('button').addClass('backuply-disabled').off('click');
						stop_modal.off('click').prop('disabled', false).addClass('backuply-disabled');
						
					}
					
					finish_btn.click( function() {
						modal.hide();
						location.reload();
					});
					
					backuply_status.has_ended = true;
				}
			}
			
			if(!backuply_status.has_ended){
				setTimeout(backuply_backup_progress, 2000);
			}
		}
	});
}

// Event handler for restore
function backuply_restorequery(form_id){
	event.preventDefault();
	
	var inputs = jQuery(form_id+' :input');
	var link = backuply_obj.ajax_url;
	
	var cnfm = confirm('Are you sure you want to Restore?');
	if(!cnfm){
		return;
	}
	
	var last_cnfm = confirm('This is the last confirmation!\nRestoration is an irreversible process, do you still want to continue?');
	if(!last_cnfm){
		return;
	}
	
	backuply_backup_style(2);
	
	var data = new Object();
	data['action'] = 'backuply_restore_curl_query';
	data['sess_key'] = backuply_obj.creating_session;
	
	inputs.each(function(){
		data[this.name] = jQuery(this).val();
		data['security'] = backuply_obj.nonce;
	});
	
	backuply_update_modal_str(true);
	
	let modal = jQuery('#backuply-backup-progress'),
		image = modal.find('.backuply-modal-bak-icon'),
		protocol = inputs.closest('form').data('protocol'),
		proto_name = inputs.closest('form').data('bak-name'),
		restore_name = modal.find('.backuply-loc-restore-name');

	image.prop('src', backuply_obj.images + (protocol ? protocol : 'local') + '.svg');
	restore_name.text('Restoring From: ' + proto_name);
	modal.find('.dashicons-no').hide();
	modal.data('process', 'restore');
	
	
	modal.find('.backuply-backup-status').empty();
	modal.find('.backuply-stop-backup').hide();
	modal.show();	
	
	backuply_progress_init(true, 'Restore');
	
	jQuery.post(link, data, function(res) {								
		backuply_backup_progress();		
		setTimeout(backuply_heartbeat, 2000);
	});
}

function backuply_heartbeat(){
	jQuery.ajax({
		method : 'GET',
		url : backuply_obj.ajax_url + '?action=backuply_creating_session&security='+backuply_obj.backuply_key+'&sess_key='+backuply_obj.creating_session,
		success : function(res){
			if(res.success == true){
				return;
			}
			
			setTimeout(backuply_heartbeat, 2000);
		},
		error: function(){
			setTimeout(backuply_heartbeat, 2000);
		}
	});
}


// Downloads the backup file
function backuply_download_backup(jEle) {
	
	let file_name = jEle.data('name'),
	progress_btn = jEle.next();

	jQuery.ajax({
		method : 'GET',
		url : backuply_obj.ajax_url,
		xhrFields : {
			responseType : 'arraybuffer'
		},
		data : {
			action : 'backuply_download_backup',
			backup_name : file_name,
			security : backuply_obj.nonce
		},
		xhr: function () {
			jEle.hide();
			progress_btn.show();

			var xhr = new window.XMLHttpRequest();
			//Download progress
			xhr.addEventListener('progress', function (evt) {

				if (evt.lengthComputable) {
					var percent_complete = evt.loaded / evt.total;
					progress_btn.text(Math.round(percent_complete * 100) + '% Downloading');
				}
			}, false);
			return xhr;
		},
		success : function(res) {
			
			if(res.hasOwnProperty('success')) {
				alert(res.message);
			}
			
			let a = document.createElement('a'),
				url = URL.createObjectURL(new Blob([res], {type : 'application/octet-stream'}));
			
			a.href = url;
			a.download = file_name;
			
			document.body.append(a);
			a.setAttribute('target', '_blank');
			a.click();
			a.remove();
			window.URL.revokeObjectURL(url);
			
			// Toggle download buttons
			progress_btn.text('0% Downloading');
			progress_btn.hide();
			jEle.show();
		}
	});
}

// Updates state of Auto backup option when Auto Backup is changed
function backuply_cron_backup_style(select_value){
	
	if(typeof select_value == 'object' && select_value.backuply_custom_cron) {
		select_value = 'custom';
	}
	
	jQuery('#backuply-custom-cron').hide();
	jQuery("#backuply_cron_checkbox").show();
	jQuery('#backup_rotation').prop('disabled', false);
	
	if(!select_value) {
		jQuery("#backuply_cron_checkbox").hide();
		jQuery('#backup_rotation').prop('disabled', true);
	}
	
	if(select_value == 'custom') {
		jQuery('#backuply-custom-cron').show();
	}
}

function backuply_close_modal(ele) {
	jQuery(ele).closest('.backuply-modal').hide();
}

function backuply_sync_backup(jEle) {

	let loc_id = jEle.prev().val(),
		tab = jEle.closest('#backuply-history'),
		spinner = tab.find('.spinner');
	
	jQuery.ajax({
		method : 'GET',
		url : backuply_obj.ajax_url + '?action=backuply_sync_backups&id='+loc_id+'&security='+backuply_obj.nonce,
		beforeSend: function() {
			spinner.addClass('is-active');
		},
		success : function(res){
		
			if(!res.success){
				if(!res.message){
					alert('Something went wrong please try later');
				}
				
				alert(res.message);
				return;
			}
			
			location.reload();
			spinner.removeClass('is-active');
			
		}
	});
}

// Loads last logs
function backuply_load_last_logs(is_restore, file_name = '', proto_id = ''){
	
	let id = '#backuply-backup-last-log';
	
	if(is_restore) {
		id = '#backuply-restore-last-log';
	}
	
  let modal = jQuery(id),
	spinner = modal.find('.spinner'),
	inside = modal.find('.backuply-last-logs-block');
	
	modal.find('.backuply-last-logs-block').empty();
	
	let ajax_url = backuply_obj.ajax_url + '?action=backuply_last_logs';
	
	if(is_restore){
		ajax_url += '&is_restore=true'; 
	} else {
		ajax_url += '&file_name=' + file_name;
		ajax_url += '&proto_id=' + proto_id;
	}
	
	ajax_url += '&security='+ backuply_obj.nonce;

	jQuery.ajax({
		method : 'GET',
		url : ajax_url,
		beforeSend: function() {
			spinner.addClass('is-active');
		},
		success: function(res) {
			spinner.removeClass('is-active');
			
			if(!res.success || !res.progress_log){
				inside.append('No log found!');
				return;
			}
			
			res.progress_log = res.progress_log.split('\n');
			
			for(let text of res.progress_log) {
				if(!text){
					continue;
				}
				
				if(!text.includes('|')) {
					continue;
				}
				
				let [log, status] = text.split('|'),
				color = '';
				
				switch(status) {
					case 'info':
						color = 'yellow';
						break;
					
					case 'error': 
						color = 'red';
						break;
						
					case 'success':
						color = 'rgb(102, 187, 106)';
						break;
				}
			
				if(status == 'uploading') {
					if(inside.find('.backuply-upload-progress')) {
						inside.find('.backuply-upload-progress').remove();
					}
					inside.append(log);
				} else {
					let html = '<p'+ (color ? ' style="color:'+color+'"' : '')+ '>';
					
					if(status == 'success') {
						html += '<span class="dashicons dashicons-saved"></span>';
					}
					
					html += log;
					html += '</p>';
					
					inside.append(html);
				}
			}
			
		}
		
	});
}

function backuply_handle_exclude_pattern(jEle){
	
	let add_btn = jEle.find('.backuply-pattern-insert');
	
	// Adds the Exclude pattern using enter
	jQuery('[name="exclude_pattern_val"]').off().on('keypress', function() {
		if(event.key == 'Enter') {
			let block = jQuery(this).closest('.backuply-exclude-pattern-block');
			block.find('.backuply-pattern-insert').trigger('click');
		}
	})
	
	function add_btn_handler() {
		let ele = jQuery(this).closest('.backuply-exclude-pattern-block'),
		type = ele.find('select'),
		pattern = ele.find('input'),
		html = '',
		type_val = type.val(),
		pattern_val = pattern.val();

		if(!pattern_val){
			alert('Add a pattern');
			return;
		}
		
		if(ele.data('type')){
			type_val = ele.data('type');
		}
		
		types = {
			'extension' : 'With specific extension',
			'beginning' : 'At beginning',
			'end' : 'At end',
			'anywhere' : 'Anywhere'
		};
		
		if(!ele.data('type')){
			html = '<div class="backuply-exclude-pattern-block" data-edit="true" data-type="'+type_val+'">'+
				'<span class="backuply-exclude-pattern-type">'+ types[type_val] + '</span>'+
				'<span class="backuply-exclude-pattern-val"><input type="text" name="exclude_pattern_val" style="width:90%;" value="'+ pattern_val + '" disabled/></span>'+
				'<span class="dashicons dashicons-trash backuply-pattern-delete" style="display:none;"></span>'+
				'<span class="dashicons dashicons-edit backuply-pattern-edit"></span>'+
				'<span class="dashicons dashicons-insert backuply-pattern-insert" style="display:none;"></span>'+
				'<span class="spinner is-active"></span>'+
			'</div>';
			
			jEle.append(html);
			
			type.val('extension');
			pattern.val('');
		}
		
		if(ele.data('type')){
			save_ele = ele;
		} else{
			save_ele = jEle.last();
		}

		let data = {pattern: pattern_val, type : type_val}
		
		if(ele.data('key')){
			data['key'] = ele.data('key');
		}

		save_rules(data, save_ele);
		
	}
	
	// Gets triggred when edit it clicked
	function edit_btn_handler() {
		let block = jQuery(this).closest('.backuply-exclude-pattern-block');
		
		change_action_state(block, 'insert');
		block.find('input').prop('disabled', false);
		
	}
	
	// Ajax request to save or update the exclude rule
	function save_rules(data, jEle) {
		
		let request_data = data;
		request_data['action'] = 'backuply_save_excludes';
		request_data['security'] = backuply_obj.nonce;

		jQuery.ajax({
			method : 'POST',
			url : backuply_obj.ajax_url,
			data : request_data,
			success: function(res){

				if(!res.success){

					if(res.message){
						if(jEle.data('type')){
							change_action_state(jEle, 'edit');
							jEle.find('input').prop('disabled', true);
						} else {
							jEle.remove();
							
						}
						alert(res.message);
						return;
					}
					
					alert('Unable to save this Exclude rule');
					return;
				}
				
				if(jEle.data('type')){
					jEle.find('input').prop('disabled', true);
					change_action_state(jEle, 'edit');
				} else {
					let new_ele = jEle.find('.backuply-exclude-pattern-block').last();
					new_ele.find('.is-active').css('display', 'none');
					new_ele.find('.is-active').removeClass('is-active');
					new_ele.find('.backuply-pattern-delete').css('display', 'block');
					new_ele.attr('data-key', res.key);
					
					// Adding event listners to new elements
					new_ele.find('.backuply-pattern-insert').off().on('click', add_btn_handler);
					new_ele.find('.backuply-pattern-edit').off().on('click', edit_btn_handler);
					new_ele.find('.backuply-pattern-delete').off().on('click', backuply_delete_exclude_rule);
				}
				
				return;
			}
		});
	}
	
	// Updates the states of icons of actions of the Exclude rule
	function change_action_state(jEle, state){
		
		if(state == 'loader'){
			jEle.find('.backuply-pattern-delete').css('display', 'none');
			jEle.find('.backuply-pattern-insert').css('display', 'none');
			jEle.find('.backuply-pattern-edit').css('display', 'none');
			jEle.find('.spinner').css('display', '');
			jEle.find('.spinner').addClass('is-active');
		} else if(state == 'edit') {
			jEle.find('.backuply-pattern-delete').css('display', '');
			jEle.find('.backuply-pattern-insert').css('display', 'none');
			jEle.find('.backuply-pattern-edit').css('display', '');
			jEle.find('.spinner').css('display', 'none');
			jEle.find('.spinner').removeClass('is-active');
		} else if(state == 'insert') {
			jEle.find('.backuply-pattern-delete').css('display', 'none');
			jEle.find('.backuply-pattern-edit').css('display', 'none');
			jEle.find('.backuply-pattern-insert').css('display', '');
			jEle.find('.spinner').css('display', 'none');
			jEle.find('.spinner').removeClass('is-active');
		}
	}
	
	
	// Event handlers
	add_btn.off().on('click', add_btn_handler);
	jEle.find('.backuply-pattern-edit').off().on('click', edit_btn_handler);
}

function backuply_delete_exclude_rule() {
	
	let conf = confirm('Are you sure you want to delete this Exclude rule');

	if(!conf){
		return
	}
	
	let jEle = jQuery(this),
	block = jEle.closest('.backuply-exclude-pattern-block'),
	key = block.data('key'),
	type = block.data('type');

	jQuery.ajax({
		method : 'GET',
		url : backuply_obj.ajax_url,
		data : {
			action : 'backuply_exclude_rule_delete',
			security : backuply_obj.nonce,
			key : key,
			type : type
		},
		success : function(res) {
			if(!res.success){
				alert('Unable to delete this Exclude rule');
				return;
			}
			
			block.remove();
		}
	});
	
}

function backuply_exclude_specific(jEle) {

	jEle.find('.backuply-exclude-add-exact').off().on('click', function() {
		event.preventDefault();
		console.log(jEle.find('.backuply-js-tree').jstree('get_selected', true));
		if(jEle.find('.backuply-js-tree').jstree('get_selected', true).length < 1){
			alert('Select a File or Folder Before adding it!');
			return;
		}

		let path = jEle.find('.backuply-js-tree').jstree('get_selected', true)[0]['id'],
		
		html = '<div class="backuply-exclude-pattern-block" data-edit="true" data-type="exact">'+
			'<span class="backuply-exclude-pattern-val" style="width:95%;">'+path+'</span>'+
			'<span class="dashicons dashicons-trash backuply-pattern-delete" style="display:none;"></span>'+
			'<span class="spinner is-active"></span>'+
		'</div>';

		jEle.append(html);
		
		jQuery.ajax({
			method : 'POST',
			url : backuply_obj.ajax_url,
			data : {
				action : 'backuply_save_excludes',
				security : backuply_obj.nonce,
				type : 'exact',
				pattern : path
			},
			success : function(res) {
				if(!res.success){

					if(res.message){
						jEle.remove();
						alert(res.message);
						return;
					}
					jEle.remove();
					alert('Unable to save this Exclude rule');
					return;
				}

				let new_ele = jEle.find('.backuply-exclude-pattern-block').last();
				new_ele.find('.is-active').css('display', 'none');
				new_ele.find('.is-active').removeClass('is-active');
				new_ele.find('.backuply-pattern-delete').css('display', 'block');
				new_ele.attr('data-key', res.key);
				new_ele.find('.backuply-pattern-delete').off().on('click', backuply_delete_exclude_rule);

				return;
			}
		})
		
	});
	
}