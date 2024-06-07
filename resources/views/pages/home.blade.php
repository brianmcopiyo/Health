@php
$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Home')

@section('content')
<h4 class="py-3 mb-2">
  <span class="text-muted fw-light">eCommerce /</span> Order List
</h4>

<h4 class="mb-4">Home Page <button type="button" class="btn btn-outline-primary" data-bs-toggle="offcanvas" data-bs-target="#offcanvasEcommerceCustomerAdd"><span class="ti ti-plus me-0 me-sm-1 mb-1 ti-xs"></span>Primary</button></h4>

<div class="card">

  <div class="card-datatable table-responsive">
    <div id="DataTables_Table_0_wrapper" class="dataTables_wrapper dt-bootstrap5 no-footer">
      <div class="mx-4 mb-3 mt-3">
        <div>
          <h4>Filter</h4>
        </div>
        <div class="row">
          <div class="col-sm-12 mb-2 col-md-8">
            <input type="text" class="form-control phone-mask" placeholder="Search..." aria-label="+(123) 456-7890" name="customerContact">
          </div>
          <div class="col-sm-12 mb-2 col-md-2">
            <input type="text" class="form-control phone-mask" placeholder="Search..." aria-label="+(123) 456-7890" name="customerContact">
          </div>
          <div class="col-sm-12 mb-2 col-md-2 btn-group" role="group">
            <button id="btnGroupDrop1" type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Dropdown</button>
            <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
              <a class="dropdown-item" href="javascript:void(0);"><i class="ti ti-download me-1"></i>Dropdown link</a>
              <a class="dropdown-item" href="javascript:void(0);"><i class="ti ti-download me-1"></i>Dropdown link</a>
            </div>
          </div>
        </div>
      </div>

      <div class="table-responsive text-nowrap border-bottom">
        <table class="table">
          <thead>
            <tr class="text-nowrap">
              <th></th>
              <th>Table heading</th>
              <th>Table heading</th>
              <th>Table heading</th>
              <th>Table heading</th>
              <th>Table heading</th>
              <th>Table heading</th>
              <th>Table heading</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody class="table-border-bottom-0">
            <tr>
              <th scope="row">1</th>
              <td>
                <a href="">
                  <div class="d-flex flex-wrap">
                    <div class="avatar me-3">
                      <img src="{{asset('assets/img/avatars/6.png')}}" alt="Avatar" class="rounded-circle" />
                    </div>
                    <div>
                      <p class="mb-0">Rebecca Godman</p>
                      <span class="text-muted">Javascript Developer</span>
                    </div>
                  </div>
                </a>
              </td>
              <td>Table cell</td>
              <td>Table cell</td>
              <td>Table cell</td>
              <td><span class="badge rounded-pill bg-label-primary">1000</span></td>
              <td>
                <div>
                  <p class="mb-1">Rebecca Godman</p>
                  <div class="progress" style="height: 8px;">
                    <div class="progress-bar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                </div>
              </td>
              <td>
                <div class="d-flex align-items-center lh-1 me-3 mb-3 mb-sm-0">
                  <span class="badge badge-dot bg-primary me-1"></span> Primary
                </div>
              </td>
              <td>
                <div class="dropdown">
                  <button class="btn p-0" type="button" id="MonthlyCampaign" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="ti ti-dots-vertical ti-sm text-muted"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end" aria-labelledby="MonthlyCampaign">
                    <a class="dropdown-item" href="javascript:void(0);">Refresh 1</a>
                    <a class="dropdown-item" href="javascript:void(0);">Download</a>
                    <a class="dropdown-item" href="javascript:void(0);">View All</a>
                  </div>
                </div>
              </td>
            </tr>
            <tr>
              <th scope="row">2</th>
              <td>
                <a href="">
                  <div class="d-flex flex-wrap">
                    <div class="avatar me-3">
                      <img src="{{asset('assets/img/avatars/6.png')}}" alt="Avatar" class="rounded-circle" />
                    </div>
                    <div>
                      <p class="mb-0">Rebecca Godman</p>
                      <span class="text-muted">Javascript Developer</span>
                    </div>
                  </div>
                </a>
              </td>
              <td>Table cell</td>
              <td>Table cell</td>
              <td>Table cell</td>
              <td><span class="badge rounded-pill bg-label-primary">1000</span></td>
              <td>
                <div>
                  <p class="mb-1">Rebecca Godman</p>
                  <div class="progress" style="height: 8px;">
                    <div class="progress-bar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                </div>
              </td>
              <td>
                <div class="d-flex align-items-center lh-1 me-3 mb-3 mb-sm-0">
                  <span class="badge badge-dot bg-secondary me-1"></span> Primary
                </div>
              </td>
              <td>
                <div class="dropdown">
                  <button class="btn p-0" type="button" id="MonthlyCampaign" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="ti ti-dots-vertical ti-sm text-muted"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end" aria-labelledby="MonthlyCampaign">
                    <a class="dropdown-item" href="javascript:void(0);">Refresh 1</a>
                    <a class="dropdown-item" href="javascript:void(0);">Download</a>
                    <a class="dropdown-item" href="javascript:void(0);">View All</a>
                  </div>
                </div>
              </td>
            </tr>
            <tr>
              <th scope="row">3</th>
              <td>
                <a href="">
                  <div class="d-flex flex-wrap">
                    <div class="avatar me-3">
                      <img src="{{asset('assets/img/avatars/6.png')}}" alt="Avatar" class="rounded-circle" />
                    </div>
                    <div>
                      <p class="mb-0">Rebecca Godman</p>
                      <span class="text-muted">Javascript Developer</span>
                    </div>
                  </div>
                </a>
              </td>
              <td>Table cell</td>
              <td>Table cell</td>
              <td>Table cell</td>
              <td><span class="badge rounded-pill bg-label-primary">1000</span></td>
              <td>
                <div>
                  <p class="mb-1">Rebecca Godman</p>
                  <div class="progress" style="height: 8px;">
                    <div class="progress-bar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                </div>
              </td>
              <td>
                <div class="d-flex align-items-center lh-1 me-3 mb-3 mb-sm-0">
                  <span class="badge badge-dot bg-success me-1"></span> Primary
                </div>
              </td>
              <td>
                <div class="dropdown">
                  <button class="btn p-0" type="button" id="MonthlyCampaign" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="ti ti-dots-vertical ti-sm text-muted"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end" aria-labelledby="MonthlyCampaign">
                    <a class="dropdown-item" href="javascript:void(0);">Refresh 1</a>
                    <a class="dropdown-item" href="javascript:void(0);">Download</a>
                    <a class="dropdown-item" href="javascript:void(0);">View All</a>
                  </div>
                </div>
              </td>
            </tr>
            <tr>
              <th scope="row">4</th>
              <td>
                <a href="">
                  <div class="d-flex flex-wrap">
                    <div class="avatar me-3">
                      <img src="{{asset('assets/img/avatars/6.png')}}" alt="Avatar" class="rounded-circle" />
                    </div>
                    <div>
                      <p class="mb-0">Rebecca Godman</p>
                      <span class="text-muted">Javascript Developer</span>
                    </div>
                  </div>
                </a>
              </td>
              <td>Table cell</td>
              <td>Table cell</td>
              <td>Table cell</td>
              <td><span class="badge rounded-pill bg-label-primary">1000</span></td>
              <td>
                <div>
                  <p class="mb-1">Rebecca Godman</p>
                  <div class="progress" style="height: 8px;">
                    <div class="progress-bar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                </div>
              </td>
              <td>
                <div class="d-flex align-items-center lh-1 me-3 mb-3 mb-sm-0">
                  <span class="badge badge-dot bg-warning me-1"></span> Primary
                </div>
              </td>
              <td>
                <div class="dropdown">
                  <button class="btn p-0" type="button" id="MonthlyCampaign" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="ti ti-dots-vertical ti-sm text-muted"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end" aria-labelledby="MonthlyCampaign">
                    <a class="dropdown-item" href="javascript:void(0);">Refresh 1</a>
                    <a class="dropdown-item" href="javascript:void(0);">Download</a>
                    <a class="dropdown-item" href="javascript:void(0);">View All</a>
                  </div>
                </div>
              </td>
            </tr>
            <tr>
              <th scope="row">5</th>
              <td>
                <a href="">
                  <div class="d-flex flex-wrap">
                    <div class="avatar me-3">
                      <img src="{{asset('assets/img/avatars/6.png')}}" alt="Avatar" class="rounded-circle" />
                    </div>
                    <div>
                      <p class="mb-0">Rebecca Godman</p>
                      <span class="text-muted">Javascript Developer</span>
                    </div>
                  </div>
                </a>
              </td>
              <td>Table cell</td>
              <td>Table cell</td>
              <td>Table cell</td>
              <td><span class="badge rounded-pill bg-label-primary">1000</span></td>
              <td>
                <div>
                  <p class="mb-1">Rebecca Godman</p>
                  <div class="progress" style="height: 8px;">
                    <div class="progress-bar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                </div>
              </td>
              <td>
                <div class="d-flex align-items-center lh-1 me-3 mb-3 mb-sm-0">
                  <span class="badge badge-dot bg-danger me-1"></span> Primary
                </div>
              </td>
              <td>
                <div class="dropdown">
                  <button class="btn p-0" type="button" id="MonthlyCampaign" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="ti ti-dots-vertical ti-sm text-muted"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end" aria-labelledby="MonthlyCampaign">
                    <a class="dropdown-item" href="javascript:void(0);">Refresh 1</a>
                    <a class="dropdown-item" href="javascript:void(0);">Download</a>
                    <a class="dropdown-item" href="javascript:void(0);">View All</a>
                  </div>
                </div>
              </td>
            </tr>
            <tr>
              <th scope="row">6</th>
              <td>
                <a href="">
                  <div class="d-flex flex-wrap">
                    <div class="avatar me-3">
                      <img src="{{asset('assets/img/avatars/6.png')}}" alt="Avatar" class="rounded-circle" />
                    </div>
                    <div>
                      <p class="mb-0">Rebecca Godman</p>
                      <span class="text-muted">Javascript Developer</span>
                    </div>
                  </div>
                </a>
              </td>
              <td>Table cell</td>
              <td>Table cell</td>
              <td>Table cell</td>
              <td><span class="badge rounded-pill bg-label-primary">1000</span></td>
              <td>
                <div>
                  <p class="mb-1">Rebecca Godman</p>
                  <div class="progress" style="height: 8px;">
                    <div class="progress-bar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                </div>
              </td>
              <td>
                <div class="d-flex align-items-center lh-1 me-3 mb-3 mb-sm-0">
                  <span class="badge badge-dot bg-info me-1"></span> Primary
                </div>
              </td>
              <td>
                <div class="dropdown">
                  <button class="btn p-0" type="button" id="MonthlyCampaign" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="ti ti-dots-vertical ti-sm text-muted"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end" aria-labelledby="MonthlyCampaign">
                    <a class="dropdown-item" href="javascript:void(0);">Refresh 1</a>
                    <a class="dropdown-item" href="javascript:void(0);">Download</a>
                    <a class="dropdown-item" href="javascript:void(0);">View All</a>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="row mx-2 mt-3">
        <div class="col-sm-12 mb-2 col-md-12">
          <div class="dataTables_info" id="DataTables_Table_0_info" role="status" aria-live="polite">Showing 31 to 40 of 100 entries</div>
        </div>

        <div class="col-sm-12 col-md-12">
          <nav aria-label="navigation">
            <ul class="pagination pagination-round pagination-secondary">
              <li class="page-item first">
                <a class="page-link" href="javascript:void(0);"><i class="ti ti-chevrons-left ti-xs"></i></a>
              </li>
              <li class="page-item prev">
                <a class="page-link" href="javascript:void(0);"><i class="ti ti-chevron-left ti-xs"></i></a>
              </li>
              <li class="page-item">
                <a class="page-link" href="javascript:void(0);">1</a>
              </li>
              <li class="page-item">
                <a class="page-link" href="javascript:void(0);">2</a>
              </li>
              <li class="page-item active">
                <a class="page-link" href="javascript:void(0);">3</a>
              </li>
              <li class="page-item">
                <a class="page-link" href="javascript:void(0);">4</a>
              </li>
              <li class="page-item">
                <a class="page-link" href="javascript:void(0);">5</a>
              </li>
              <li class="page-item next">
                <a class="page-link" href="javascript:void(0);"><i class="ti ti-chevron-right ti-xs"></i></a>
              </li>
              <li class="page-item last">
                <a class="page-link" href="javascript:void(0);"><i class="ti ti-chevrons-right ti-xs"></i></a>
              </li>
            </ul>
          </nav>
        </div>
      </div>
    </div>
  </div>

  <!-- Offcanvas to add new customer -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasEcommerceCustomerAdd" aria-labelledby="offcanvasEcommerceCustomerAddLabel">
    <div class="offcanvas-header">
      <h5 id="offcanvasEcommerceCustomerAddLabel" class="offcanvas-title">Add Customer</h5>
      <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body mx-0 flex-grow-0">
      <form class="ecommerce-customer-add pt-0 fv-plugins-bootstrap5 fv-plugins-framework" id="eCommerceCustomerAddForm" onsubmit="return false" novalidate="novalidate">
        <div class="ecommerce-customer-add-basic mb-3">
          <h6 class="mb-3">Basic Information</h6>
          <div class="mb-3 fv-plugins-icon-container">
            <label class="form-label" for="ecommerce-customer-add-name">Name*</label>
            <input type="text" class="form-control" id="ecommerce-customer-add-name" placeholder="John Doe" name="customerName" aria-label="John Doe">
          <div class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback"></div></div>
          <div class="mb-3 fv-plugins-icon-container">
            <label class="form-label" for="ecommerce-customer-add-email">Email*</label>
            <input type="text" id="ecommerce-customer-add-email" class="form-control" placeholder="john.doe@example.com" aria-label="john.doe@example.com" name="customerEmail">
          <div class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback"></div></div>
          <div>
            <label class="form-label" for="ecommerce-customer-add-contact">Mobile</label>
            <input type="text" id="ecommerce-customer-add-contact" class="form-control phone-mask" placeholder="+(123) 456-7890" aria-label="+(123) 456-7890" name="customerContact">
          </div>
        </div>

        <div class="pt-3">
          <button type="submit" class="btn btn-primary me-sm-3 me-1 data-submit waves-effect waves-light">Add</button>
          <button type="reset" class="btn btn-label-danger waves-effect" data-bs-dismiss="offcanvas">Discard</button>
        </div>
      <input type="hidden"></form>
    </div>
  </div>
</div>
@endsection
