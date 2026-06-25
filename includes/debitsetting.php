
	<div class="w-container">
        <div class="row">
            <div class="col-lg-3"></div>
                <div class="col-lg-9">                                    
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="srch">
                                <div class="form-group" >
                                    <br>           
                                    <h3>Debit Advise Settings</h3>
                                    <label  class="label label-primary" for="l1">Update Bank Contact Information!</label>
                                    <label  class="label label-primary" for="l2">Register Bank Information!</label>
                                    <label  class="label label-primary" for="l2">Register Bank Contact Information!</label>   
                                </div>
                            </div>
                           
                        </div>
                    </div>
                    <hr>
                    <button type="button" id="new" class="btn btn-primary" data-toggle="modal" data-target="#myModal"> <i class="fa fa-plus"></i> Register </button>
                     <button type="button" id="refreshData" class="btn btn-primary" > <i class="fa fa-refresh"></i></button> <br>
                    <table class="table table-striped table-hover">
                      <thead>
                        <tr>
                          <th scope="col">#</th>
                          <th scope="col">Name</th>
                           <th scope="col">Position</th>
                          <th scope="col">Branch</th>
                          <th scope="col">CA/SA Account</th>
                          <th scope="col">Action</th>
                        </tr>
                      </thead>
                      <tbody id="tblBank">
                        
                      </tbody>
                    </table>
                     <!-- Modal -->
                    <div class="modal fade" id="myModalUpdate" role="dialog">
                        <div class="modal-dialog  modal-dialog-centered">
                            <!-- Modal content-->
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    <h4 class="modal-title">Updating Bank Contact Information</h4>
                                </div>
                                <div class="modal-body" style="padding-left:0px;padding-right:0px">
                                    <div class="row" >
                                        <div class="col-lg-12" style="padding-left:0px;padding-right:0px">
                                            <!-- //<div class="row"> -->
                                                <div class="col-lg-2" >
                                                    <div class="form-group">
                                                        <label for="email">Salutation:</label>
                                                        <select name="" id="salutationUpdate" class="form-control">
                                                            <option value="Ms.">Ms.</option>
                                                            <option value="Mrs.">Mrs.</option>
                                                            <option value="Mr.">Mr.</option>
                                                        </select>
                                                            
                                                    </div>
                                                </div>

                                                <div class="col-lg-4">
                                                    <div class="form-group">
                                                        <label for="email">First Name:</label>
                                                        <input type="text" class="form-control" id="fnameUpdate">
                                                    </div>
                                                </div>

                                                <div class="col-lg-2">
                                                    <div class="form-group">
                                                        <label for="email">Initial:</label>
                                                        <input type="text" class="form-control" id="initialUpdate">
                                                    </div>
                                                </div>

                                                <div class="col-lg-4">
                                                    <div class="form-group">
                                                        <label for="email">Last Name:</label>
                                                        <input type="text" class="form-control" id="lnameUpdate">
                                                    </div>
                                                </div>
                                            <!-- </div> -->
                                                
                                        </div>
                                        <div class="col-lg-12" style="padding-left:0px;padding-right:0px">
                                            <!-- //<div class="row"> -->
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label for="email">Position:</label>
                                                        <input type="text" class="form-control" id="positionUpdate">
                                                    </div>
                                                </div>

                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label for="email">Branch:</label>
                                                        <input type="text" class="form-control" id="branchUpdate">
                                                    </div>
                                                </div>
                                            <!-- </div> -->
                                                
                                        </div>

                                        <div class="col-lg-12" style="padding-left:0px;padding-right:0px">
                                            <!-- //<div class="row"> -->
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label for="email">City Address:</label>
                                                        <input type="text" class="form-control" id="cityaddressUpdate">
                                                    </div>
                                                </div>

                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label for="email">Other Branch:</label>
                                                        <input type="text"  id="otherbranchUpdate" class="form-control"  >
                                                    </div>
                                                </div>
                                            <!-- </div> -->
                                                
                                        </div>

                                    </div>
                                </div>
                             
                                <div class="modal-footer">
                                <div  id="resultUpdate" class="alert" style="display:none;float:left" ></div>
                                    <button type="button" class="btn btn-primary" id="btnRegisterUpdate">Save Changes</button>
                                    <button type="button" class="btn btn-default" data-dismiss="modal" >Close</button>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                     <!-- modal update info -->  
                     <!-- Modal -->
                    <div class="modal fade" id="myModal" role="dialog">
                        <div class="modal-dialog  modal-dialog-centered">
                            <!-- Modal content-->
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    <h4 class="modal-title">Register Bank Contact Information</h4>
                                </div>
                                <div class="modal-body" style="padding-left:0px;padding-right:0px">
                                    <div class="row" >
                                        <div class="col-lg-12" style="padding-left:0px;padding-right:0px">
                                            <!-- //<div class="row"> -->
                                                <div class="col-lg-2" >
                                                    <div class="form-group">
                                                        <label for="email">Salutation:</label>
                                                        <select name="" id="salutation" class="form-control">
                                                            <option value="Ms.">Ms.</option>
                                                            <option value="Mrs.">Mrs.</option>
                                                            <option value="Mr.">Mr.</option>
                                                        </select>
                                                            
                                                    </div>
                                                </div>

                                                <div class="col-lg-4">
                                                    <div class="form-group">
                                                        <label for="email">First Name:</label>
                                                        <input type="text" class="form-control" id="fname">
                                                    </div>
                                                </div>

                                                <div class="col-lg-2">
                                                    <div class="form-group">
                                                        <label for="email">Initial:</label>
                                                        <input type="text" class="form-control" id="initial">
                                                    </div>
                                                </div>

                                                <div class="col-lg-4">
                                                    <div class="form-group">
                                                        <label for="email">Last Name:</label>
                                                        <input type="text" class="form-control" id="lname">
                                                    </div>
                                                </div>
                                            <!-- </div> -->
                                                
                                        </div>
                                        <div class="col-lg-12" style="padding-left:0px;padding-right:0px">
                                            <!-- //<div class="row"> -->
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label for="email">Position:</label>
                                                        <input type="text" class="form-control" id="position">
                                                    </div>
                                                </div>

                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label for="email">Branch:</label>
                                                        <input type="text" class="form-control" id="branch">
                                                    </div>
                                                </div>
                                            <!-- </div> -->
                                                
                                        </div>

                                        <div class="col-lg-12" style="padding-left:0px;padding-right:0px">
                                            <!-- //<div class="row"> -->
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label for="email">City Address:</label>
                                                        <input type="text" class="form-control" id="cityaddress">
                                                    </div>
                                                </div>

                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label for="email">Other Branch:</label>
                                                        <input type="text"  id="otherbranch" class="form-control"  >
                                                    </div>
                                                </div>
                                            <!-- </div> -->
                                                
                                        </div>

                                    </div>
                                    
                                
                                

                                </div>
                             
                                <div class="modal-footer">
                                <div  id="result" class="alert" style="display:none;float:left" ></div>
                                    <button type="button" class="btn btn-primary" id="btnRegister">Register</button>
                                    <button type="button" class="btn btn-default" data-dismiss="modal" >Close</button>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                     <!-- modal update info -->  
                         <div class="modal fade" id="myModalCA" role="dialog">
                            <div class="modal-dialog  modal-dialog-centered">
                            
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        <h5 class="modal-title caname"></h4>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <form action="" autocomplete=off>
                                                <div class="col-lg-12">
                                                    <div class="row">
                                                        <div class="col-lg-6">
                                                            <input type="text" class="form-control" name="caNumber" id="caNumber" placeholder="Account Number">
                                                        </div>
                                                        
                                                        <div class="col-lg-3">
                                                            <button type="button" class="btn btn-primary btn-sm" id="saveca"> <i class="fa fa-plus"></i></button>
                                                           
                                                        </div>
                                                        
                                                    </div>
                                                </div>
                                            <hr>
                                                <div class="col-lg-12">
                                                    <div class="row">
                                                        <table class="table table-striped table-hover">
                                                            <thead>
                                                                <tr>
                                                                <th scope="col">#</th>
                                                                <th scope="col">CA/SA Account</th>
                                                                <th scope="col">Action</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="tblca">
                                                                
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                       
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                       
                                    </div>
                                </div>
                            </div>
                        </div> 

                </div>
            </div>
        </div>
    </div> 


<!-- <div class="modal fade" id="myModal" role="dialog">-->
<!--<div class="modal-dialog  modal-dialog-centered">-->

<!--    <div class="modal-content">-->
<!--        <div class="modal-header">-->
<!--            <button type="button" class="close" data-dismiss="modal">&times;</button>-->
<!--            <h4 class="modal-title">Modal Header</h4>-->
<!--        </div>-->
<!--        <div class="modal-body">-->
<!--            <p>Some text in the modal.</p>-->
<!--        </div>-->
<!--        <div class="modal-footer">-->
<!--            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>-->
<!--        </div>-->
<!--    </div>-->
<!--</div>-->
<!--</div> -->-->
