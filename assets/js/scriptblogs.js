$(document).ready(function()
{
  $("#bsave").click(function(){ 
        var data=$("#newblog").serialize();

    if (!$("#titlepost").val())
            {
              $("#titlepost").addClass("error"); 
            }

        else if (!$("#comment").val())
          {
              $("#comment").addClass("error");
          }

    else if ($("#category").val()==="0")
      {
          $("#category").addClass("error"); 
      }

    else if (!$("#file").val())
      {
        $("#file").addClass("error"); 
      }
                
            else
                {

                     $("#comment").removeClass("error");
                     $("#titlepost").removeClass("error");
                     $("#category").removeClass("error");
                     $("#file").removeClass("error"); 
                    $.ajax({
                            url:'query/Query-InsertBlog.php', 
                            type:'post',
                            data:data,

                            success:function(res){
                                     
                                if(res==1)
                                    {
                                        $("#messageshow").show();

                                        var fd = new FormData(); 
                                        var files = $('#file')[0].files[0];
                                        var empID = "63";
                                        fd.append('file',files);
                                        $.ajax({
                                                url:'uploadblogspicture.php?q=' + empID,
                                                type:'post',
                                                data:fd,
                                                contentType: false,
                                                processData: false,
                                                success:function(response){}
                                                });

                                                                      //reload grid
                                                    var xmlhttp = new XMLHttpRequest();
                                                    var idname1 = "63"; 
                                                    $("#blogdata").empty();
                                                    xmlhttp.onreadystatechange = function() {
                                                    if (this.readyState == 4 && this.status == 200) {
                                                        document.getElementById("blogdata").innerHTML = this.responseText;
                                                                        }
                                                                        };
                                                        xmlhttp.open("GET", "query/QuerySearchBlog.php?q=" + idname1, true);
                                                        xmlhttp.send();

                                                                  }

                                                       

                                                          }
                            });

                                              

                }           
      });

  //new line
  //reload
  $("#reloaddata").click(function(){ 

    var dtfr = ($("#datefrom").val()); 
    var dtto = ($("#dateto").val()); 
            $.ajax({
                    url:'query/Query-refresbloglist.php?srchblog',
                        type:'post',
                        data: { dtfrom : dtfr, dtto : dtto },
                    success:function(res){
                                                          
                        $("#blogdata").empty();
                        $("#blogdata").append(res);
                                        }
                   });              
     });
//update
  $(".updatingblog_save").click(function(){ 
        var label = $('#postID');
        var dataa = label.attr('text'); 
  
        //alert();
        if (!$("#uptitlepost").val())
        {
            $("#uptitlepost").addClass("error"); 
        }
        else if (!$("#upcomment").val())
        {
            $("#upcomment").addClass("error");
        }
        else if ($("#upcategory").val()=="0")
        {
            $("#upcategory").addClass("error"); 
        }
        else
        {
            $("#upcomment").removeClass("error");
            $("#uptitlepost").removeClass("error");
            $("#upcategory").removeClass("error");
            //newline
                var data=$("#updateblog1").serialize();
                $.ajax({
                        url:'query/Query-UpdateBlog.php?pid='+ dataa, 
                        type:'post',
                        data:data,
                        success:function(res){ 
                         
                        }
                      });   

                if (!$("#upfile").val())
                {
                  //alert("nothing selected");
                }  
                else
                {
                    var fd = new FormData(); 
                    var files = $('#upfile')[0].files[0];
                    var empID = "10";
                    fd.append('file',files);
                    $.ajax({
                               url:'uploadblogspicture.php?q=' + empID + '&pid=' + dataa,
                                type:'post',
                                data:fd,
                                contentType: false,
                                processData: false,
                                success:function(response){  
                                alert("Blog successfully updated!");
                                                          }
                            });
                }
                var xmlhttp = new XMLHttpRequest();
                var idname1 = "63"; 
                $("#blogdata").empty();
                    xmlhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                    document.getElementById("blogdata").innerHTML = this.responseText;
                                                                                                  
                                                                    }
                                                            };
                    xmlhttp.open("GET", "query/QuerySearchBlog.php?q=" + idname1, true);
                    xmlhttp.send();

                                                                               
        }
  });


});