<div class="modal fade" id="nav-config" tabindex="-1" aria-hidden="true">
							<div class="modal-dialog drawer drawer-end">
								<div class="drawer-content">
									<div class="drawer-header">
										<h4>Theme Config</h4>
										<span class="close-btn close-btn-default" role="button" data-bs-dismiss="modal">
											<svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
												<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
											</svg>
										</span>
									</div>
									<div class="drawer-body">
										<div class="flex flex-col h-full justify-between">
											<div class="flex flex-col gap-y-10 mb-6">
												<div class="flex items-center justify-between">
													<div>
														<h6>Dark Mode</h6>
														<span>Switch theme to dark mode</span>
													</div>
													<div>
														<label class="switcher">
															<input name="dark-mode-toggle" type="checkbox" value="">
															<span class="switcher-toggle"></span>
														</label>
													</div>
												</div>
												<div class="flex items-center justify-between">
													<div>
														<h6>Direction</h6>
														<span>Select a direction</span>
													</div>
													<div class="input-group">
														<button id="dir-ltr-button" class="btn btn-default btn-sm btn-active">
															LTR
														</button>
														<button id="dir-rtl-button" class="btn bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 active:bg-gray-100 dark:active:bg-gray-500 dark:active:border-gray-500 text-gray-600 dark:text-gray-100 radius-round h-9 px-3 py-2 text-sm">
															RTL
														</button>
													</div>
												</div>
												<div>
													<h6 class="mb-3">Nav Mode</h6>
													<div class="inline-flex">
														<label class="radio-label inline-flex mr-3" for="nav-mode-radio-default">
															<input id="nav-mode-radio-default" type="radio" value="default" name="nav-mode-radio-group" class="radio text-primary-600" checked>
															<span>Default</span>
														</label>
														<label class="radio-label inline-flex mr-3" for="nav-mode-radio-themed">
															<input id="nav-mode-radio-themed" type="radio" value="themed" name="nav-mode-radio-group" class="radio text-primary-600">
															<span>Themed</span>
														</label>
													</div>
												</div>
												<div>
													<h6 class="mb-3">Nav Mode</h6>
													<select id="theme-select" class="input input-sm focus:ring-primary-600 focus-within:ring-primary-600 focus-within:border-primary-600 focus:border-primary-600">
														<option value="primary" selected>Indigo</option>
														<option value="red">Red</option>
														<option value="orange">Orange</option>
														<option value="amber">Amber</option>
														<option value="yellow">Yellow</option>
														<option value="lime">Lime</option>
														<option value="green">Green</option>
														<option value="emerald">Emerald</option>
														<option value="teal">Teal</option>
														<option value="cyan">Cyan</option>
														<option value="sky">Sky</option>
														<option value="blue">Blue</option>
														<option value="violet">Violet</option>
														<option value="purple">Purple</option>
														<option value="fuchsia">Fuchsia</option>
														<option value="pink">Pink</option>
														<option value="rose">Rose</option>
													</select>
												</div>
												<div>
													<h6 class="mb-3">Layout</h6>
													<div class="segment w-full">
														<div class="grid grid-cols-3 gap-4 w-full">
															<div class="text-center" id="layout-classic">
																<div class="flex items-center border rounded-md border-gray-200 dark:border-gray-600 cursor-pointer select-none w-100 hover:ring-1 hover:ring-primary-600 hover:border-primary-600 relative min-h-[80px] w-full">
																	<img src="img/thumbs/layouts/classic.jpg" alt="" class="rounded-md dark:hidden">
																	<img src="img/thumbs/layouts/classic-dark.jpg" alt="" class="rounded-md hidden dark:block">
																</div>
																<div class="mt-2 font-semibold">Classic</div>
															</div>
															<div class="text-center" id="layout-modern">
																<div class="flex items-center border rounded-md dark:border-gray-600 cursor-pointer select-none w-100 ring-1 ring-primary-600 border-primary-600 hover:ring-1 hover:ring-primary-600 hover:border-primary-600 relative min-h-[80px] w-full">
																	<img src="img/thumbs/layouts/modern.jpg" alt="" class="rounded-md dark:hidden">
																	<img src="img/thumbs/layouts/modern-dark.jpg" alt="" class="rounded-md hidden dark:block">
																	<svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" class="text-primary-600 absolute top-2 right-2 text-lg" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
																		<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
																	</svg>
																</div>
																<div class="mt-2 font-semibold">Modern</div>
															</div>
															<div class="text-center" id="layout-stackedSide">
																<div class="flex items-center border rounded-md border-gray-200 dark:border-gray-600 cursor-pointer select-none w-100 hover:ring-1 hover:ring-primary-600 hover:border-primary-600 relative min-h-[80px] w-full">
																	<img src="img/thumbs/layouts/stackedSide.jpg" alt="" class="rounded-md dark:hidden">
																	<img src="img/thumbs/layouts/stackedSide-dark.jpg" alt="" class="rounded-md hidden dark:block">
																</div>
																<div class="mt-2 font-semibold">Stacked Side</div>
															</div>
															<div class="text-center" id="layout-simple">
																<div class="flex items-center border rounded-md border-gray-200 dark:border-gray-600 cursor-pointer select-none w-100 hover:ring-1 hover:ring-primary-600 hover:border-primary-600 relative min-h-[80px] w-full">
																	<img src="img/thumbs/layouts/simple.jpg" alt="" class="rounded-md dark:hidden">
																	<img src="img/thumbs/layouts/simple-dark.jpg" alt="" class="rounded-md hidden dark:block">
																</div>
																<div class="mt-2 font-semibold">Simple</div>
															</div>
															<div class="text-center" id="layout-decked">
																<div class="flex items-center border rounded-md border-gray-200 dark:border-gray-600 cursor-pointer select-none w-100 hover:ring-1 hover:ring-primary-600 hover:border-primary-600 relative min-h-[80px] w-full">
																	<img src="img/thumbs/layouts/decked.jpg" alt="" class="rounded-md dark:hidden">
																	<img src="img/thumbs/layouts/decked-dark.jpg" alt="" class="rounded-md hidden dark:block">
																</div>
																<div class="mt-2 font-semibold">Decked</div>
															</div>
														</div>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>