$(function(){
 const cfg = window.STICKY_REPORT_CONFIG || {endpoint:'report_ajax.php',csrf:''};
 $('.confirm-delete').on('click',function(e){if(!confirm('Are you sure? This action cannot be undone.'))e.preventDefault();});
 $(document).on('click','.toggle-detail',function(){const target=$($(this).data('target'));target.toggle();$(this).text(target.is(':visible')?'Hide details':'View details');});
 $('.column-filter-open').on('click',function(){const modal=$('#'+$(this).data('modal'));modal.addClass('is-open').attr('aria-hidden','false');modal.find('input[type="checkbox"]').first().trigger('focus');});
 $('.column-modal-close').on('click',function(){const modal=$(this).closest('.column-modal');modal.removeClass('is-open').attr('aria-hidden','true');});
 $(document).on('keydown',function(e){if(e.key==='Escape')$('.column-modal.is-open').removeClass('is-open').attr('aria-hidden','true');});
 function reportPanel(kind){return $('#'+(kind==='sub'?'subscription-report':'initial-report'));}
 function setLoading(kind,on){const panel=reportPanel(kind);panel.toggleClass('is-loading',on);panel.find('button[type="submit"]').prop('disabled',on);panel.find('.report-loading').attr('aria-hidden',on?'false':'true');}
 function showReportMessage(kind,message,isError){$('#'+(kind==='sub'?'sub-message':'initial-message')).text(message).toggleClass('error-text',!!isError);}
 function formDataFor(kind){const form=$('.ajax-report-filter[data-kind="'+kind+'"]');return {_csrf:cfg.csrf,kind:kind,[kind+'_start']:form.find('[name="'+kind+'_start"]').val(),[kind+'_end']:form.find('[name="'+kind+'_end"]').val()};}
 function updateInitialHead(columns){const labels={order_id:'Order ID',parent_id:'Parent ID',time_stamp:'Report Date (time_stamp)',campaign_id:'Campaign ID',first_name:'First Name',last_name:'Last Name',email_address:'Email',order_total:'Order Total',order_status:'Status',main_product_id:'Main Product ID',billing_cycle:'Billing Cycle',is_recurring:'Recurring',customer_id:'Customer ID'};let html='';(columns||Object.keys(labels)).forEach(k=>{if(labels[k])html+='<th>'+ $('<div>').text(labels[k]).html()+'</th>';});html+='<th>Details</th>';$('#initial-table-head').html(html);}
 function loadReport(kind){
  const data=formDataFor(kind);setLoading(kind,true);showReportMessage(kind,'Loading report…',false);
  return $.ajax({url:cfg.endpoint,method:'POST',dataType:'json',data:data,timeout:240000})
   .done(function(r){if(!r||!r.ok){showReportMessage(kind,(r&&r.message)||'Unable to load report.',true);return;}if(kind==='initial'){ $('#initial-table-body').html(r.html);$('#initial-total').text(r.count);if(r.columns)updateInitialHead(r.columns);}else{$('#sub-table-body').html(r.html);$('#sub-total').text(r.count);}showReportMessage(kind,r.message||'Report loaded.',false);})
   .fail(function(xhr,textStatus){const msg=(xhr.responseJSON&&xhr.responseJSON.message)||(textStatus==='timeout'?'The dashboard request timed out. Increase PHP/web-server timeouts or narrow the date range.':'Report request failed. Check the server configuration and try again.');showReportMessage(kind,msg,true);})
   .always(function(){setLoading(kind,false);});
 }
 $('.ajax-report-filter').on('submit',function(e){e.preventDefault();loadReport($(this).data('kind'));});
 // A date change refreshes only that table. Debouncing prevents duplicate calls when editing both dates.
 const dateTimers={};
 $('.ajax-report-filter input[type="date"]').on('change',function(){
  const form=$(this).closest('.ajax-report-filter'),kind=form.data('kind');
  clearTimeout(dateTimers[kind]);
  dateTimers[kind]=setTimeout(function(){
   const start=form.find('[name="'+kind+'_start"]').val(),end=form.find('[name="'+kind+'_end"]').val();
   if(start&&end&&start<=end)loadReport(kind);
   else showReportMessage(kind,'Please choose a valid date range (From must be on or before To).',true);
  },250);
 });
 $('.ajax-column-form').on('submit',function(e){
  e.preventDefault();const form=$(this),kind=form.data('kind');
  if(!form.find('input[name="columns[]"]:checked').length){alert('Choose at least one field.');return;}
  setLoading(kind,true);showReportMessage(kind,'Updating fields and reloading report…',false);
  $.ajax({url:cfg.endpoint,method:'POST',dataType:'json',data:form.serialize(),timeout:30000})
   .done(function(r){
    if(!r||!r.ok){showReportMessage(kind,(r&&r.message)||'Unable to save fields.',true);setLoading(kind,false);return;}
    $('#'+(kind==='sub'?'sub':'initial')+'-columns-modal').removeClass('is-open').attr('aria-hidden','true');
    loadReport(kind);
   })
   .fail(function(xhr){showReportMessage(kind,(xhr.responseJSON&&xhr.responseJSON.message)||'Unable to save report fields.',true);setLoading(kind,false);});
 });
 // On initial page load, fetch today's summary and today's rows for both reports via AJAX.
 // Requests are sequenced to avoid hitting Sticky.io with three large calls simultaneously.
 $('#summary-message').text('Loading today’s summary…');
 $.ajax({url:cfg.endpoint,method:'POST',dataType:'json',data:{_csrf:cfg.csrf,kind:'summary'},timeout:240000})
  .done(function(r){if(!r||!r.ok){$('#summary-message').removeClass('info').addClass('error').text((r&&r.message)||'Unable to load today’s summary.');return;}$('#summary-initial').text(Number(r.initial).toLocaleString());$('#summary-subscription').text(Number(r.subscription).toLocaleString());$('#summary-other').text(Number(r.other).toLocaleString());$('#summary-message').removeClass('info error').text('Today’s summary loaded.');})
  .fail(function(xhr,textStatus){const msg=(xhr.responseJSON&&xhr.responseJSON.message)||(textStatus==='timeout'?'Today’s summary request timed out.':'Unable to load today’s summary.');$('#summary-message').removeClass('info').addClass('error').text(msg);})
  .always(function(){
   loadReport('initial').always(function(){loadReport('sub');});
  });
});
