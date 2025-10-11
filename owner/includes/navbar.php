    <!-- [ Header Topbar ] start -->
    <header class="pc-header">
        <div class="header-wrapper">
            <!-- [Mobile Media Block] start -->
            <div class="me-auto pc-mob-drp">
                <ul class="list-unstyled">
                    <!-- ======= Menu collapse Icon ===== -->
                    <li class="pc-h-item pc-sidebar-collapse">
                        <a href="#" class="pc-head-link ms-0" id="sidebar-hide">
                            <i class="ti ti-menu-2"></i>
                        </a>
                    </li>
                    <li class="pc-h-item pc-sidebar-popup">
                        <a href="#" class="pc-head-link ms-0" id="mobile-collapse">
                            <i class="ti ti-menu-2"></i>
                        </a>
                    </li>
                </ul>
            </div>
            <!-- [Mobile Media Block end] -->
            <div class="ms-auto">
                <ul class="list-unstyled">
                    <li class="dropdown pc-h-item header-user-profile">
                        <a
                            class="pc-head-link dropdown-toggle arrow-none me-0"
                            data-bs-toggle="dropdown"
                            href="#"
                            role="button"
                            aria-haspopup="false"
                            data-bs-auto-close="outside"
                            aria-expanded="false">
                            <img
                                src="<?= $base_url ?>/assets/img/Logo.png"
                                alt="user-image"
                                class="user-avtar" />
                            <span> <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username']) ?></span>
                        </a>
                        <div
                            class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex mb-1">
                                    <div class="flex-shrink-0">
                                        <img
                                            src="<?= $base_url ?>/assets/img/Logo.png"
                                            alt="user-image"
                                            class="user-avtar wid-35" />
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1"> <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username']) ?></h6>
                                        <span> <?= htmlspecialchars($_SESSION['role'] ?? '-') ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <ul
                                class="nav drp-tabs nav-fill nav-tabs"
                                id="mydrpTab"
                                role="tablist">

                                <li hidden class="nav-item" role="presentation">
                                    <button
                                        class="nav-link"
                                        id="drp-t2"
                                        data-bs-toggle="tab"
                                        data-bs-target="#drp-tab-2"
                                        type="button"
                                        role="tab"
                                        aria-controls="drp-tab-2"
                                        aria-selected="false">
                                        <i class="ti ti-settings"></i> Setting
                                    </button>
                                </li>
                            </ul>
                            <div class="tab-content" id="mysrpTabContent">
                                <div
                                    class="tab-pane fade show active"
                                    id="drp-tab-1"
                                    role="tabpanel"
                                    aria-labelledby="drp-t1"
                                    tabindex="0">
                                    <a href="<?= $base_url ?>/owner/profile/index.php" class="dropdown-item">
                                        <i class="ti ti-user"></i>
                                        <span>Lihat Profil</span>
                                    </a>

                                    <a href="<?= $base_url ?>/auth/logout.php" class="dropdown-item text-danger">
                                        <i class="ti ti-power"></i>
                                        <span>Logout</span>
                                    </a>
                                </div>
                                <div
                                    class="tab-pane fade"
                                    id="drp-tab-2"
                                    role="tabpanel"
                                    aria-labelledby="drp-t2"
                                    tabindex="0">
                                    <a href="#!" class="dropdown-item">
                                        <i class="ti ti-help"></i>
                                        <span>Support</span>
                                    </a>
                                    <a href="#!" class="dropdown-item">
                                        <i class="ti ti-user"></i>
                                        <span>Account Settings</span>
                                    </a>
                                    <a href="#!" class="dropdown-item">
                                        <i class="ti ti-lock"></i>
                                        <span>Privacy Center</span>
                                    </a>
                                    <a href="#!" class="dropdown-item">
                                        <i class="ti ti-messages"></i>
                                        <span>Feedback</span>
                                    </a>
                                    <a href="#!" class="dropdown-item">
                                        <i class="ti ti-list"></i>
                                        <span>History</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </header>
    <!-- [ Header ] end -->