   function myrelfunction(c){
             var element = c;
             var element2 = document.getElementById("btnice");

             element2.classList.remove("btn-success");
             element2.classList.add("btn-danger");
             document.querySelectorAll('button').forEach(function(node){
                  node.value = 'Next'
              });

             element.classList.remove("btn-danger");
             element.classList.add("btn-success");

          }  
          function myremovetr(v){
             v.parentNode.parentNode.parentNode.removeChild(v.parentNode.parentNode);
          }
            
          $(document).ready(function(){
                
                  // input change
                $("input").keyup(function(){
                  $(this).css("text-transform", "capitalize");
                });

               $('#empID').on("keyup input", function(){
                      /* Get input value on change */
                  var inputVal = $(this).val();
                  var resultDropdown = $(".dv-livesearch");
                  if(inputVal.length){
              
                      $.get("query/Query-idchecker.php", {term: inputVal}).done(function(data){
                          // Display the returned data in browser
                          // alert(data);
                      });
                  } else{
                      resultDropdown.empty();
                  }
               });

             /* ---- same helpers as Enroll Employee (script-newemployee.js) ----
                Copied rather than loading that file: it also renumbers the employee
                when the company changes and its Save creates a new employee. */

             // add new department (modal pre-fills the employee's company)
             $("#depselect").click(function(){
                 if ($("#empcompid option:selected").val()==""){
                     $('#modalWarning').modal('toggle');
                     $('#modalWarning .alert').html("Please Select Company First");
                     return false;
                 }
                 $("#compid").empty().append("<option value='" + $("#empcompid option:selected").val() + "'>" + $("#empcompid option:selected").text() + "</option>");
             });
             $(".btndepartment").click(function(){
                 if ($("#depname").val()==""){
                     $('#modalWarning').modal('toggle');
                     $('#modalWarning .alert').html("Please Fill up required Fields!");
                     return;
                 }
                 var cmid = $("#compid option:selected").val();
                 $.post('query/query-maintenance.php?newempDepartment', $(".frmdep").serialize(), function(data){
                     if (data==1){
                         $('#modalWarning').modal('toggle');
                         $('#modalWarning .alert').html("Department is Already in the Database!");
                     }else{
                         $(".DepEmp").empty().append(data);
                         $(".DepEmp .dep" + cmid).show();
                         $('#modaladddep').modal('toggle');
                     }
                 });
             });

             // add new position (needs a department first)
             $(".Pos_Sel").click(function(){
                 if ($(".DepEmp option:selected").val()==""){
                     $('#modalWarning').modal('toggle');
                     $('#modalWarning .alert').html("Please Select Department First");
                     return false;
                 }
                 $("#dep").empty().append("<option value='" + $(".DepEmp option:selected").val() + "'>" + $(".DepEmp option:selected").text() + "</option>");
                 $("#comchange").empty().append("<option value='" + $("#empcompid option:selected").val() + "'>" + $("#empcompid option:selected").text() + "</option>");
             });
             $(".btnposition").click(function(){
                 if ($("#comchange").val()=="" || $("#dep").val()=="" || $("#pos").val()=="" || $("#joblevel").val()==""){
                     $('#modalWarning').modal('toggle');
                     $('#modalWarning .alert').html("Please Fill up required Fields!");
                     return false;
                 }
                 $.post('query/query-maintenance.php?newempPosition', $(".frmpos").serialize(), function(data){
                     if (data==1){
                         $('#modalWarning').modal('toggle');
                         $('#modalWarning .alert').html("Position is already in the Record!");
                     }else{
                         $("#idempposition").empty().append(data);
                         $("#modaladdpos").modal("toggle");
                     }
                 });
             });

             // citizenship / religion suggestions
             function suggest($input, $list, key){
                 $input.keyup(function(){
                     if ($(this).val()==""){ $list.hide(); return; }
                     $list.show();
                     $.post('query/query-maintenance.php?' + key, { data: $(this).val() }, function(data){
                         $list.empty().append(data);
                     });
                 });
             }
             suggest($("#empcitizen"), $(".cl-citizen"), 'citizenship');
             suggest($("#empreligion"), $(".cl-reli"), 'religion');
             $(document).on("click", ".ctzn-a", function(){ $("#empcitizen").val($(this).text()); $(".cl-citizen").hide(); });
             $(document).on("click", ".rel-a",  function(){ $("#empreligion").val($(this).text()); $(".cl-reli").hide(); });
             // close the suggestion lists when clicking elsewhere
             $(document).on("click", function(e){
                 if (!$(e.target).closest(".ne-ac").length) { $(".cl-citizen, .cl-reli").hide(); }
             });

             // Save: validate → employee record → family details (+ photo if one was chosen)
             $("#submit-form").click(function(){
                var $btn = $(this);
                var missing = window.ueValidateAll ? window.ueValidateAll(true) : 0;
                if (missing > 0) {
                    var tab = window.ueFirstIncompleteTab && window.ueFirstIncompleteTab();
                    if (tab && window.ueShowTab) { window.ueShowTab(tab); }
                    swal("Incomplete", "Please fill up the " + missing + " required field(s) highlighted in red.");
                    return;
                }

                var empID = $('#empID').val();
                var fam = { name: [], a: [], rel: [], conno: [], ice: [] };
                $('.tbl-relationship tbody tr').each(function(){
                    var td = $(this).children('td');
                    fam.name.push($.trim(td.eq(0).text()));
                    fam.a.push($.trim(td.eq(1).text()));
                    fam.rel.push($.trim(td.eq(2).text()));
                    fam.conno.push($.trim(td.eq(3).text()));
                    fam.ice.push($.trim(td.eq(4).text()));
                });

                // Employees without "Update 201 Files": send the edits to HR for approval
                if ($('#fdata').data('mode') === 'request') {
                    var family = fam.name.map(function(n, i){
                        return { name: n, address: fam.a[i], relationship: fam.rel[i], contact: fam.conno[i], ice: fam.ice[i] };
                    });
                    $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Submitting…');
                    $.ajax({ url: 'query/Query-requestProfileChange.php', type: 'POST', dataType: 'json',
                             data: $("#fdata").serialize() + '&' + $.param({ family: JSON.stringify(family) }) })
                      .done(function(){
                          swal("Submitted", "Your changes were sent to HR for approval.", "success")
                            .then(function(){ location.reload(); });
                      })
                      .fail(function(xhr){
                          $btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane"></i> Submit for approval');
                          var msg = (xhr.responseJSON && xhr.responseJSON.message) || "Could not submit your request. Please try again.";
                          swal("Not submitted", msg, xhr.status === 422 ? "warning" : "error");
                      });
                    return;
                }

                $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving…');
                var done = function(){ $btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save changes'); };
                var fail = function(msg){ done(); swal("Not saved", msg || "Something went wrong. Please try again.", "error"); };
                var failXhr = function(xhr){ fail(xhr && $.trim(xhr.responseText || '')); };   // e.g. 401 "session expired"

                $.post('query/Query-updateEmployee.php', $("#fdata").serialize())
                  .done(function(resp){
                      // the endpoint prints nothing on success, an exception message on failure
                      if ($.trim(resp) !== '') { fail($.trim(resp)); return; }

                      var calls = [
                          $.post('update-fdetails.php', {
                              name: JSON.stringify(fam.name), a: JSON.stringify(fam.a), rel: JSON.stringify(fam.rel),
                              conno: JSON.stringify(fam.conno), ice: JSON.stringify(fam.ice), empID: empID
                          })
                      ];
                      var file = $('#file')[0] && $('#file')[0].files[0];
                      if (file) {
                          var fd = new FormData();
                          fd.append('file', file);
                          calls.push($.ajax({ url: 'uploadpicture.php?q=' + encodeURIComponent(empID), type: 'post',
                                              data: fd, contentType: false, processData: false }));
                      }
                      $.when.apply($, calls)
                        .done(function(){ done(); swal("Saved", "Employee profile updated.", "success"); })
                        .fail(function(xhr){ fail("The profile was saved, but family details or the photo could not be updated." +
                                                  (xhr && xhr.responseText ? " " + $.trim(xhr.responseText) : "")); });
                  })
                  .fail(failXhr);
             });

    });