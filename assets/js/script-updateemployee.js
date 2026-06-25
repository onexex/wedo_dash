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

                 $('#wrkschmon').change(function(){
                if ($("#ifch").prop('checked')){
                 if ($(this).val()!=""){
                        $("#wrkschtues").val($("#wrkschmon").val());
                        $("#wrkschwed").val($("#wrkschmon").val());
                        $("#wrkschthu").val($("#wrkschmon").val());
                        $("#wrkschfri").val($("#wrkschmon").val());
                        $("#wrkschsat").val($("#wrkschmon").val());
                        $("#wrkschsun").val($("#wrkschmon").val());
                      }
                  }
              });
            
              $('#ifch').click(function(){
                      /* Get input value on change */
                    if ($(this).prop('checked')){
                      if ($("#wrkschmon").val()!=""){
                        $("#wrkschtues").val($("#wrkschmon").val());
                        $("#wrkschwed").val($("#wrkschmon").val());
                        $("#wrkschthu").val($("#wrkschmon").val());
                        $("#wrkschfri").val($("#wrkschmon").val());
                        $("#wrkschsat").val($("#wrkschmon").val());
                        $("#wrkschsun").val($("#wrkschmon").val());
                      }
                    }
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

             $("#submit-form").click(function(){
           
                var miss1=0;
                if ($("#empfname").val() == ""){
                miss1=miss1+1;
                }
                   if ($("#empdob").val() == ""){
                           miss1=miss1+1;
                        }
                if ($("#emplname").val() == ""){
                 miss1=miss1+1;
                }
                  if ($("#empgender").val() == ""){
                 miss1=miss1+1;
                }
                  if ($("#empcs").val() == ""){
                 miss1=miss1+1;
                }
                  if ($("#pempdistrict").val() == "" || $("#pempcity").val() == "" || $("#pempprovince").val() == "" || $("#pempzipcode").val() == "" || $("#pempcountry").val() == ""){
             
                 miss1=miss1+1;
                }
                
                if (miss1>=0){
             
                    // $("#btn-ci").val() = 'Contact Information (' + miss1 + ')';
                    document.getElementById("btn-ci").innerHTML = 'Contact Information (' + miss1 + ')';
                    
                } 

                var miss2=0;
                if ($("#empID").val() == ""){
                 miss2=miss2+1;
                }
                if ($("#empstatus").val()==""){
             
                   miss2=miss2+1;
                }
                  if ($("#empdesccode").val()==""){
                   miss2=miss2+1;
                }
                   if ($("#empdepartment").val()==""){
                   miss2=miss2+1;
                }
                   if ($("#idempposition").val()==""){
                   miss2=miss2+1;
                }
                if ($("#empis").val()==""){
                   miss2=miss2+1;
                }
                 if ($("#empdth").val()==""){

                   miss2=miss2+1;
                }
                  if ($("#emprdid").val()==""){
                   miss2=miss2+1;
                }
                  if ($("#empwsid").val()==""){
                   miss2=miss2+1;
                 
                }
                if ($("#empbasic").val()==""){
                   miss2=miss2+1;
                }
                if (miss2>=0){
             
                    // $("#btn-ci").val() = 'Contact Information (' + miss1 + ')';
                    document.getElementById("btn-es").innerHTML = 'Employment Status (' + miss2 + ')';
                  
                } 

                if (miss2==0 && miss1==0)
                { 

                  
                        var fd = new FormData(); 
                        var files = $('#file')[0].files[0];
                        var empID = $('#empID').val();

                        fd.append('file',files);
                        $.ajax({
                            url:'uploadpicture.php?q=' + empID,
                            type:'post',
                            data:fd,
                            contentType: false,
                            processData: false,
                            success:function(response){  
                              alert("Successfully Updated !");
                  
                            }
                        });
                       
                         var data=$("#fdata").serialize();

                        $.ajax({

                          url:'query/Query-updateEmployee.php', 
                          data:data,
                          type:'POST',
                                                               
                           success:function(data){
                               
                               alert(data);
                           }
                        });

                      var lastRowId = $('.tbl-relationship tr:last').attr("id"); /*finds id of the last row inside table*/
                      var name = new Array(); 
                      var a = new Array();
                      var rel = new Array(); 
                      var conno= new Array();
                      var ice= new Array();


                      for ( var i = 1; i <= lastRowId; i++) {
                      name.push($("#"+i+" .name"+i).html()); /*pushing all the names listed in the table*/
                      a.push($("#"+i+" .add"+i).html()); /*pushing all the emails listed in the table*/
                      rel.push($("#"+i+" .rel"+i).html());
                      conno.push($("#"+i+" .con"+i).html());
                      ice.push($("#"+i+" .ice"+i).html());

                      }
                      var sendName = JSON.stringify(name); 
                      var sendAdd = JSON.stringify(a);
                      var sendRel = JSON.stringify(rel); 
                      var sendCon = JSON.stringify(conno);
                      var sendIce = JSON.stringify(ice); 

                      var empID = $('#empID').val(); 
                      
                      $.ajax({
                      url: "update-fdetails.php",
                      type: "post",
                      data: {name : sendName , a : sendAdd , rel : sendRel , conno : sendCon , ice : sendIce , empID : empID},
                      success : function(data){
                       /* alerts the response from php.*/
                      
                      }
                      });
                        // location.reload();

                }
                else{
                  alert("Please Fill up required Fields!");
                }
                });
              
    });