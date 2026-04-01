                            <footer class="footer flex items-center  py-4 px-4 sm:px-6 md:px-8 border-t border-gray-200 mt-auto">
    <div class="flex flex-col sm:flex-row items-center justify-between w-full text-sm text-gray-600 gap-2 sm:gap-0">
        <span class="text-center sm:text-left">
           <span>Copyright © <?php echo date('Y'); ?> <span class="font-semibold">Cocoonbaby</span></span>
        </span>
        <div class="flex flex-col sm:flex-row items-center text-center sm:text-right gap-1 sm:gap-2">
            <a href="javascript:void(0)" class="hover:underline text-gray-600">Terms &amp; Conditions</a>
            <span class="hidden sm:inline text-gray-400">|</span>
            <a href="javascript:void(0)" class="hover:underline text-gray-600">Privacy &amp; Policy</a>
        </div>
    </div>
</footer>

							<!-- Footer end -->
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<script>
		    const imagebox = document.getElementById('imageUpload');
		    if(imagebox){
		        imagebox.addEventListener('change', function (event) {
                    
                    const file = event.target.files[0];
                    const previewImg = document.getElementById('previewImage');
            
                    if (file && file.type.startsWith('image/')) {
                        const reader = new FileReader();
            
                        reader.onload = function (e) {
                            previewImg.src = e.target.result;
                        };
            
                        reader.readAsDataURL(file);
                    } else {
                        // Optional fallback if non-image is selected
                        previewImg.src = 'img/others/upload.png';
                    }
                });
		    }
            



		</script>

		<!-- Core Vendors JS -->
		<script src="./public/js/vendors.min.js"></script>

		<!-- Other Vendors JS -->
        <!-- <script src="./public/vendors/jqvmap/jquery.vmap.js"></script>
        <script src="./public/vendors/jqvmap/maps/jquery.vmap.world.js"></script> -->

		<!-- Page js -->
        <!-- <script src="./public/js/pages/crm-dashboard.js"></script> -->

		<!-- Core JS -->
		<script src="./public/js/app.min.js"></script>
<!-- <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script> -->
		<!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> -->
     <!-- Select2 JS (after jQuery) -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="./public/js/notifications.js?v=1.3"></script>
	</body>

</html>