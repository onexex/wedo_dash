
	 <div class="w-container">
        <div class="row">
          <div class="col-lg-3"></div>
         <!-- website content -->
         <div class="col-lg-9">
                                <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">User Role</h4>
         		   <div class="row">
                <div class="col-lg-6">
                       <div class="srch">
                  <div class="form-group" >
                        <label for="fname">Search Employee Lastname: </label>
                        <input type="text" class="form-control" name="txtempname"  id="txtemp" required="required" placeholder="Search Employee">
                  </div>
                  <div id="empdetails">
                      
                  </div>
             </div>
                </div>
             </div>


             	<h5 class="empaccessr"></h5	>
             	<input type="hidden" id="empidar" name="empidar">
             	<input type="hidden" id="emponoff" name="emponoff">
				<table class="table">
					<thead>
						<tr>
							<th>Name</th>
              <th>User Role</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody id="accr">
						 <!-- The Modal
                   -->      <div class="modal" id="acchange">
                          <div class="modal-dialog">
                            <div class="modal-content">
                            
                            
                               <div class="modal-header">
                                    <h4 class="modal-title">Are you sure you want</h4>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                  </div>
                              <!-- Modal body -->
                              <div class="modal-body">
                              
                              </div>
                              
                            <!-- Modal footer -->
                            <div class="modal-footer">
                              <button type="button" id="" class="btn btn-success btnacyes">Yes</button>
                              <button type="button" class="btn btn-danger" data-dismiss="modal">No</button>
                            </div>
                            
                          </div>
                        </div>
                      </div>
					</tbody>
				</table>
         </div>
     </div>
 </div>