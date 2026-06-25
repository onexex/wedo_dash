$(document).ready(function(){

		//check the btn value and find the SQL INSERT in the query-meaintenanace.php
		$(".btnleave").click(function(){
			var data=$(".addingfrmleaveval").serialize();

			$.ajax({
                          url:'query/query-maintenance.php?addtypeofleave', 
                          type:'post',
                          data:data,

                          success:function(qwe){
                            
                              
                          		if (qwe==1){
                          		
                          			$('#modalWarning').modal('toggle');
     								$('#modalWarning .alert').html("Cant Add this Leave Type");	
                          		}
                          		else {
                          				$('#newform').modal('toggle');
                          			$('#modalWarning').modal('toggle');
     								$('#modalWarning .alert').html("Successfully Saved");	
                          			}	
                          		}
                });
 
		});
		  $(document).on("click", ".btnleaveupdate", function(){
		});
		$(document).on("click", ".deleteparrel", function(){
		    alert("dsd");
		});
		$(".btneoval").click(function(){
			var data=$(".frmeoval").serialize();
			$.ajax({
                          url:'query/query-maintenance.php?addeoval', 
                          type:'post',
                          data:data,

                          success:function(qwe){
                          			$('#newform').modal('toggle');
                          			$('#modalWarning').modal('toggle');
     								$('#modalWarning .alert').html("Successfully Saved");
     								location.reload();	
                          }	
                });
		});
		$(".btnobval").click(function(){
			var data=$(".frmobval").serialize();
			$.ajax({
                          url:'query/query-maintenance.php?addobval', 
                          type:'post',
                          data:data,

                          success:function(qwe){
                          			$('#newform').modal('toggle');
                          			$('#modalWarning').modal('toggle');
     								$('#modalWarning .alert').html("Successfully Saved");
     								location.reload();	
                          }	
                });
		});
		$(".btngraceper").click(function(){
				var data=$(".frmllilo").serialize();
				if ($("#gperiod").val()==""){
					$('#modalWarning').modal('toggle');
     				$('#modalWarning .alert').html("Please Input Grace Period");	
				}
				else{

				$.ajax({
                          url:'query/query-maintenance.php?addgraceperiod', 
                          type:'post',
                          data:data,

                          success:function(qwe){
                          
                          			$('#newform').modal('toggle');
                          			$('#modalWarning').modal('toggle');
     								$('#modalWarning .alert').html("Successfully Saved");
     								location.reload();	
                          }	
                });
					
				}
		});
		$(".btnholiday").click(function(){
				var data=$(".frmholiday").serialize();

				$.ajax({
                          url:'query/query-maintenance.php?addholidaylogger', 
                          type:'post',
                          data:data,

                          success:function(qwe){
                    
                          			$('#newform').modal('toggle');
                          			$('#modalWarning').modal('toggle');
     								$('#modalWarning .alert').html("Successfully Saved");	
     								location.reload();
                          }	
                });
		});

	  $('input[type="checkbox"]').click(function(){
	          if($(this).is(":checked")){
	           $("#notallowed").append(" " + $(this).val());
	          }else{
	          	var text = $("#notallowed").text().replace($(this).val(), '');
    			$('#notallowed').text(text);
	          }

        });

	$(document).on('click','.btnchangelval',function(){
		var cid = $(this).attr("id");
		var names = $(this).text();

		// $("#lvview").empty();
							var xmlhttp = new XMLHttpRequest();
					        xmlhttp.onreadystatechange = function() {
					        if (this.readyState == 4 && this.status == 200) {
					          document.getElementById("lvview").innerHTML = this.responseText;
					        }
					            };
					          xmlhttp.open("GET", "query/query-searchmaintenance.php?lvviewer=" + cid + "&nm=" + names, true);
					          xmlhttp.send();
	});
	$(document).on('click','.btnsaveotm',function(){
	    
			var data=$(".frmot").serialize();
			$.ajax({
                          url:'query/query-maintenance.php?otfsm', 
                          type:'post',
                          data:data,

                           success:function(data){
                           	 	location.reload();
                           }
            });       	
	});
	$("#fbf").change(function(){
		if ($(this).val()==0){
			$('#inputextbefore').prop('readonly', false);
			$('#inputextbefore').val("0");
		}
		else{
			$('#inputextbefore').prop('readonly', true);	
			$('#inputextbefore').val("0");
		}
	});
	$("#faf").change(function(){
		if ($(this).val()==0){
			$('#inputextafter').prop('readonly', false);
			$('#inputextafter').val("0");
		}
		else{
			$('#inputextafter').prop('readonly', true);	
			$('#inputextafter').val("0");
		}
	});

	$(".dropdown-item").click(function(){
			var cmid = $(this).attr("id");
			$(".company-name").text($(this).text());
			 
                          	 $("#maintbody").empty();
							var xmlhttp = new XMLHttpRequest();
					        xmlhttp.onreadystatechange = function() {
					        if (this.readyState == 4 && this.status == 200) {

					          document.getElementById("maintbody").innerHTML = this.responseText;
					            }
					            };
					          xmlhttp.open("GET", "query/query-searchmaintenance.php?otmaint=" + cmid, true);
					          xmlhttp.send();
                               // $('#daract').val("");
                           	  
	});

	$('.btnleavet').click(function() {
		if ($("#lname").val()==""){
			$('#modalWarning').modal('toggle');
     		$('#modalWarning .alert').html("Please Fill this Leave Name !");	
			
		}
		else{
			var data=$(".frmtleave").serialize();
			$.ajax({
                          url:'query/query-maintenance.php?typeofleave', 
                          type:'post',
                          data:data,

                           success:function(data){
                           		$('#newform').modal('toggle');
                           		$('#modalWarning').modal('toggle');
                           		$('#modalWarning .alert').html("Successfully Saved !");	
                           		 $("#maintbody").empty();
								var xmlhttp = new XMLHttpRequest();
						        xmlhttp.onreadystatechange = function() {
						        if (this.readyState == 4 && this.status == 200) {

						          document.getElementById("maintbody").innerHTML = this.responseText;
						            }
						            };
						          xmlhttp.open("GET", "query/query-searchmaintenance.php?typeofl", true);
						          xmlhttp.send();
                           }
            });       	
		}
	});

	$('.btnagency').click(function() {
		if ($("#agencyno").val() == "" || $("#agencystatus").val() == "" || $("#agencyname").val() == ""){
			$('#modalWarning').modal('toggle');
     		$('#modalWarning .alert').html("Please Input empty fields !");	
			return false;
		}
		else{

		 var data=$(".frmagency").serialize();

                        $.ajax({
                          url:'query/query-maintenance.php?agency', 
                          type:'post',
                          data:data,

                           success:function(data){
                          	 $("#maintbody").empty();
							var xmlhttp = new XMLHttpRequest();
					        xmlhttp.onreadystatechange = function() {
					        if (this.readyState == 4 && this.status == 200) {

					          document.getElementById("maintbody").innerHTML = this.responseText;
					            }
					            };
					          xmlhttp.open("GET", "query/query-searchmaintenance.php?agency", true);
					          xmlhttp.send();
                               // $('#daract').val("");
                           }
                        });
             
		}
		

	});

	$('.btncompany').click(function() {
		var str = $("#compno").val();
		if (/\s/.test(str)) {
			    // It has any kind of whitespace
			$('#modalWarning').modal('toggle');
     		$('#modalWarning .alert').html("Enter a valid Company ID");	
			return false;
		}

		if ($("#compname").val() == "" || $("#compno").val() == "" || $("#comcolor").val() == "" || $("#comologopath").val() == ""){
			$('#modalWarning').modal('toggle');
     		$('#modalWarning .alert').html("Please Input empty fields !");	
			return false;
		}
		else{


		 var fd = new FormData(); 
                        var files = $('#comlogopath')[0].files[0];
                        var empID = $('#compno').val();
                     
                        fd.append('file',files);
                        $.ajax({
                            url:'query-uploadcompanypicture.php?q=' + empID,
                            type:'post',
                            data:fd,
                            contentType: false,
                            processData: false,
                            success:function(response){ 
                          		
                            }
                        });	
                        
                        var ext = $("#comlogopath").val().split('.').pop();
    					var data=$(".frmcompany").serialize();

                        $.ajax({
                          url:'query/query-maintenance.php?company=' + ext, 
                          type:'post',
                          data:data,
                         
                           success:function(data){
                          		$("#maintbody").empty();
								 var xmlhttp = new XMLHttpRequest();
						          xmlhttp.onreadystatechange = function() {
						        if (this.readyState == 4 && this.status == 200) {
						        	
						          document.getElementById("maintbody").innerHTML = this.responseText;
						            }
						            };
						          xmlhttp.open("GET", "query/query-searchmaintenance.php?company", true);
						          xmlhttp.send();
                               // $('#daract').val("");
                           }
                        });
            
		}
		

	});
	$('.btndepartment').click(function() {
		if ($("#depname").val() == ""){
			$('#modalWarning').modal('toggle');
     		$('#modalWarning .alert').html("Please Input empty fields !");	
			return false;
		}
		else{

		 	var data=$(".frmdep").serialize();

                        $.ajax({
                          url:'query/query-maintenance.php?department', 
                          type:'post',
                          data:data,

                           success:function(data){
                           	
                          	  	$("#maintbody").empty();
								var xmlhttp = new XMLHttpRequest();
						        xmlhttp.onreadystatechange = function() {
						        if (this.readyState == 4 && this.status == 200) {
										 document.getElementById("maintbody").innerHTML = this.responseText;
						         }
						        };
						        xmlhttp.open("GET", "query/query-searchmaintenance.php?department", true);
						        xmlhttp.send();
                               // $('#daract').val("");
                           }
                        });
            
		}
		

	});
	$('.btnhmo').click(function() {

		if ($("#hmoid").val() == "" || $("#hmoname").val() == ""){
			$('#modalWarning').modal('toggle');
     		$('#modalWarning .alert').html("Please Input empty fields !");	
			return false;
		}
		else{

		 var data=$(".frmhmo").serialize();

                        $.ajax({
                          url:'query/query-maintenance.php?hmo', 
                          type:'post',
                          data:data,

                           success:function(data){
                          	 $("#maintbody").empty();
							 var xmlhttp = new XMLHttpRequest();
					          xmlhttp.onreadystatechange = function() {
					        if (this.readyState == 4 && this.status == 200) {

					          document.getElementById("maintbody").innerHTML = this.responseText;
					            }
					            };
					          xmlhttp.open("GET", "query/query-searchmaintenance.php?hmo", true);
					          xmlhttp.send();
					          $("#hmoid").val(""); 
					           $("#hmoname").val("");
                               // $('#daract').val("");
                           }
                        });
        
		}
		

	});
	//SIL LOAN
	$('.btnsilloan').click(function() {
		if ($("#loanam").val()==""){
			$('#modalWarning').modal('toggle');
     		$('#modalWarning .alert').html("Please Input empty fields !");	
			return false;
		}
		else{
			var data=$(".frmsilloan").serialize();
			 $.ajax({
                 	url:'query/query-maintenance.php?silloan', 
                  	type:'post',
                  	data:data,
					success:function(data){
						$("#maintbody").empty();
						 var xmlhttp = new XMLHttpRequest();
				        xmlhttp.onreadystatechange = function() {
				        	if (this.readyState == 4 && this.status == 200) {
							document.getElementById("maintbody").innerHTML = this.responseText;
				            }
				            };
				          xmlhttp.open("GET", "query/query-searchmaintenance.php?silloan", true);
				          xmlhttp.send();
				          $("#nameoffamily").val() = "";
				          $("#dob").val() = "";
						$("#newform").modal('hide');
					}
				});
		}
	});
	//famimly details 
	$('.btnsavepfdet').click(function() {
		if ($("#nameoffamily").val() == "" || $("#dob").val() == ""){
			$('#modalWarning').modal('toggle');
     		$('#modalWarning .alert').html("Please Input empty fields !");	
			return false;
		}
		else{
			var data=$(".frmparentalfam").serialize();
			 $.ajax({
                 	url:'query/query-maintenance.php?familyparental', 
                  	type:'post',
                  	data:data,
                  	 error: function(xhr, status, error) {
                    alert(xhr.responseText);
                    },
					success:function(data){
						$("#maintbody").empty();
						 var xmlhttp = new XMLHttpRequest();
				        xmlhttp.onreadystatechange = function() {
				        	if (this.readyState == 4 && this.status == 200) {
							document.getElementById("maintbody").innerHTML = this.responseText;
				            }
				            };
				          xmlhttp.open("GET", "query/query-searchmaintenance.php?familyparental", true);
				          xmlhttp.send();
				          $("#nameoffamily").val() = "";
				          $("#dob").val() = "";
						$("#newform").modal('hide');
					}
				});
		}
	});
	// end of family details
	///sss add
	$('.btnssss').click(function() {

		if ($("#SSSC").val() == "" || $("#salaryfrom").val() == "" || $("#salaryto").val() == "" || $("#SSER").val() == "" || $("#SSEE").val() == ""  || $("#SSEC").val() == ""){
			$('#modalWarning').modal('toggle');
     		$('#modalWarning .alert').html("Please Input empty fields !");	
			return false;
		}
		else{

		 var data=$(".frmsssssave").serialize();

                        $.ajax({
                          url:'query/query-maintenance.php?sss', 
                          type:'post',
                          data:data,

                           success:function(data){
                          	 $("#maintbody").empty();
							 var xmlhttp = new XMLHttpRequest();
					          xmlhttp.onreadystatechange = function() {
					        if (this.readyState == 4 && this.status == 200) {

					          document.getElementById("maintbody").innerHTML = this.responseText;
					            }
					            };
					          xmlhttp.open("GET", "query/query-searchmaintenance.php?sss", true);
					          xmlhttp.send();
					          $("#hmoid").val(""); 
					           $("#hmoname").val("");
							   $("#SSSC").val("");
							   $("#salaryfrom").val("");
							   $("#salaryto").val(""); 
							   $("#SSER").val(""); 
							   $("#SSEE").val("");
							   $("#SSEC").val("");
							   $("#newform").modal('hide');
							   // $('#daract').val("");
                           }
                        });
        
		}
		

	});

	//end sss
	//pagibig
	
	$('.btnpagibig').click(function() {
			
		if ($("#ee").val() == "" || $("#er").val() == ""){
			$('#modalWarning').modal('toggle');
     		$('#modalWarning .alert').html("Please Input empty fields !");	
			return false;
		}
		else{
			var data=$(".frmpagibigssave").serialize();

			$.ajax({
			  url:'query/query-maintenance.php?pagibig', 
			  type:'post',
			  data:data,

			   success:function(data){
				   $("#maintbody").empty();
				 var xmlhttp = new XMLHttpRequest();
				  xmlhttp.onreadystatechange = function() {
				if (this.readyState == 4 && this.status == 200) {

				  document.getElementById("maintbody").innerHTML = this.responseText;
					}
					};
				  xmlhttp.open("GET", "query/query-searchmaintenance.php?pagibig", true);
				  xmlhttp.send();
				  $("#ee").val(""); 
				   $("#er").val("");
				   $("#newform").modal('hide');
				   // $('#daract').val("");
			   }
			});
		}
	
	});
	// end pagibig

	// philhealth
	$('.btnphilhealth').click(function() {
			
		if ($("#PHSB").val() == "" || $("#SalaryFrom").val() == "" || $("#SalaryTo").val() == "" || $("#PHEE").val() == "" || $("#PHER").val() == ""){
			$('#modalWarning').modal('toggle');
     		$('#modalWarning .alert').html("Please Input empty fields !");	
			return false;
		}
		else{
			var data=$(".frmphilhealthssave").serialize();

			$.ajax({
			  url:'query/query-maintenance.php?philhealth', 
			  type:'post',
			  data:data,

			   success:function(data){
				   $("#maintbody").empty();
				 var xmlhttp = new XMLHttpRequest();
				  xmlhttp.onreadystatechange = function() {
				if (this.readyState == 4 && this.status == 200) {

				  document.getElementById("maintbody").innerHTML = this.responseText;
					}
					};
				  xmlhttp.open("GET", "query/query-searchmaintenance.php?philhealth", true);
				  xmlhttp.send();
					$("#PHSB").val() = "";
					$("#SalaryFrom").val() = ""; 
					$("#SalaryTo").val() = "";
					$("#PHEE").val() = "";
					$("#PHER").val() = "";
	
				   $("#newform").modal('hide');
				   // $('#daract').val("");
			   }
			});
		}
	
	});
	// end of philhealth

	$('.btnestat').click(function() {

		if ($("#empcode").val() == "" || $("#empstat").val() == ""){
			$('#modalWarning').modal('toggle');
     		$('#modalWarning .alert').html("Please Input empty fields !");	
			return false;
		}
		else{

		 var data=$(".frmstat").serialize();

                        $.ajax({
                          url:'query/query-maintenance.php?employeestatus', 
                          type:'post',
                          data:data,

                           success:function(data){
                          	 $("#maintbody").empty();
							 var xmlhttp = new XMLHttpRequest();
					          xmlhttp.onreadystatechange = function() {
					        if (this.readyState == 4 && this.status == 200) {

					          document.getElementById("maintbody").innerHTML = this.responseText;
					            }
					            };
					          xmlhttp.open("GET", "query/query-searchmaintenance.php?employeestatus", true);
					          xmlhttp.send();
					          $("#empcode").val(""); 
					           $("#empstat").val("");
                               // $('#daract').val("");
                           }
                        });
        
		}
		

	});
	$('.btnjoblevel').click(function() {

		if ($("#jid").val() == "" || $("#joblevel").val() == ""){
			$('#modalWarning').modal('toggle');
     		$('#modalWarning .alert').html("Please Input empty fields !");	
			return false;
		}
		else{

		 var data=$(".frmjoblevel").serialize();

                        $.ajax({
                          url:'query/query-maintenance.php?joblevel', 
                          type:'post',
                          data:data,

                           success:function(data){
                           	if (data==1){
                           		$('#modalWarning').modal('toggle');
     							$('#modalWarning .alert').html("Job Level ID is Already Used !");	
                 				return false;
                           	}else{
                           		 $("#maintbody").empty();
							 var xmlhttp = new XMLHttpRequest();
					          xmlhttp.onreadystatechange = function() {
					        if (this.readyState == 4 && this.status == 200) {

					          document.getElementById("maintbody").innerHTML = this.responseText;
					            }
					            };
					          xmlhttp.open("GET", "query/query-searchmaintenance.php?joblevel", true);
					          xmlhttp.send();
					          
					          	$('#newform').modal('toggle');
					          $("#jid").val(""); 
					           $("#joblevel").val("");
                           	}
                          	
                               // $('#daract').val("");
                           }
                        });
        
		}
		

	});
	$('.btnposition').click(function() {

		if ($("#comchange").val() == "" || $("#dep").val() == "" || $("#pos").val() == "" || $("#joblevel").val() == ""){
			$('#modalWarning').modal('toggle');
     		$('#modalWarning .alert').html("Please Input empty fields !");	
			return false;
		}
		else{

		 var data=$(".frmpos").serialize();

                        $.ajax({
                          url:'query/query-maintenance.php?position', 
                          type:'post',
                          data:data,

                           success:function(data){
                          	 $("#maintbody").empty();
							 var xmlhttp = new XMLHttpRequest();
					          xmlhttp.onreadystatechange = function() {
					        if (this.readyState == 4 && this.status == 200) {

					          document.getElementById("maintbody").innerHTML = this.responseText;
					            }
					            };
					          xmlhttp.open("GET", "query/query-searchmaintenance.php?position", true);
					          xmlhttp.send();
					        	$("#comchange").val("");
					        	 $("#dep").val("");
					        	 $("#pos").val("");
					        	 $("#joblevel").val("");
                               // $('#daract').val("");
                           }
                        });
        
		}
		

	});


		$('.btnrelation').click(function() {

		if ($("#relname").val() == ""){
			$('#modalWarning').modal('toggle');
     		$('#modalWarning .alert').html("Please Input empty fields !");	
			return false;
		}
		else{

		 var data=$(".frmrelationship").serialize();

                        $.ajax({
                          url:'query/query-maintenance.php?relation', 
                          type:'post',
                          data:data,

                           success:function(data){
                          	 $("#maintbody").empty();
							 var xmlhttp = new XMLHttpRequest();
					         xmlhttp.onreadystatechange = function() {
					        if (this.readyState == 4 && this.status == 200) {

					          document.getElementById("maintbody").innerHTML = this.responseText;
					            }
					            };
					          xmlhttp.open("GET", "query/query-searchmaintenance.php?relation", true);
					          xmlhttp.send();
					        	$("#relname").val("");
                               // $('#daract').val("");
                           }
                        });
        
		}
		

	});

		$('.btnclassifi').click(function() {

		if ($("#classcode").val() == "" && $("#classdesc").val() == ""){
			$('#modalWarning').modal('toggle');
     		$('#modalWarning .alert').html("Please Input empty fields !");	
			return false;
		}
		else{

		 var data=$(".frmclassifi").serialize();

                        $.ajax({
                          url:'query/query-maintenance.php?classification', 
                          type:'post',
                          data:data,

                           success:function(data){
                          	 $("#maintbody").empty();
							 var xmlhttp = new XMLHttpRequest();
					         xmlhttp.onreadystatechange = function() {
					        if (this.readyState == 4 && this.status == 200) {

					          document.getElementById("maintbody").innerHTML = this.responseText;
					            }
					            };
					          xmlhttp.open("GET", "query/query-searchmaintenance.php?classification", true);
					          xmlhttp.send();
					        	$("#classcode").val("");
					        	$("#classdesc").val("");
                               // $('#daract').val("");
                           }
                        });
        
		}
		

	});

	$('.btnworktime').click(function() {

		if ($("#timefrom").val() == "" || $("#timeto").val() == "" || $("#tcross").val() == "" ){
	
				$('#modalWarning').modal('toggle');
     			$('#modalWarning .alert').html("Please Input empty fields !");	
		return false;
		}
		else{

		 var data=$(".frmworktime").serialize();

                        $.ajax({
                          url:'query/query-maintenance.php?worktime', 
                          type:'post',
                          data:data,

                           success:function(data){
                          	 $("#maintbody").empty();
							 var xmlhttp = new XMLHttpRequest();
					         xmlhttp.onreadystatechange = function() {
					        if (this.readyState == 4 && this.status == 200) {

					          document.getElementById("maintbody").innerHTML = this.responseText;
					            }
					            };
					          xmlhttp.open("GET", "query/query-searchmaintenance.php?worktime", true);
					          xmlhttp.send();
					        	$("#timefrom").val("");
					        	$("#timeto").val("");
                               // $('#daract').val("");
                           }
                        });
        
		}
		

	});

		$('.btnworkday').click(function() {

		if ($("#dayfrom").val() == "" && $("#dayto").val() == ""){
				$('#modalWarning').modal('toggle');
     			$('#modalWarning .alert').html("Please Input empty fields !");	
		return false;
		}
		else{

		 var data=$(".frmworkday").serialize();

                        $.ajax({
                          url:'query/query-maintenance.php?workday', 
                          type:'post',
                          data:data,

                           success:function(data){
                          	 $("#maintbody").empty();
							 var xmlhttp = new XMLHttpRequest();
					         xmlhttp.onreadystatechange = function() {
					        if (this.readyState == 4 && this.status == 200) {

					          document.getElementById("maintbody").innerHTML = this.responseText;
					            }
					            };
					          xmlhttp.open("GET", "query/query-searchmaintenance.php?workday", true);
					          xmlhttp.send();
                               // $('#daract').val("");
                           }
                        });
        
		}
		

	});

	//jquery display departments in position
	$( "#comchange" ).change(function() {
		var sval = $( "#comchange" ).val();
		$("#dep option").hide();

		$("#dep " + "#" + sval).show();

	});
	$( ".cmchange" ).change(function() {

		var sval = $(this).val();
		$("#dep option").hide();
		
		$("#dep " + "#d" + sval).show();

	});
});