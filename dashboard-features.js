// Dashboard Features JavaScript - Enhanced

// Global variables
let currentUser = null;
let lastNotificationCount = 0;

// --- INITIALIZATION ---
document.addEventListener('DOMContentLoaded', function () {
    initializeDashboardFeatures();
    setupQuickActions();
    setupModalHandlers();
    setupMobileNavigation();
    startNotificationPolling();
});

// --- DASHBOARD FEATURES INITIALIZATION ---
function initializeDashboardFeatures() {
    // Initialize FAB
    const fab = document.getElementById('fab');
    if (fab) {
        fab.addEventListener('click', function () {
            openModal('quick-actions-modal');
        });
        fab.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                openModal('quick-actions-modal');
            }
        });
    }

    // Initialize close buttons
    document.querySelectorAll('.close-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                closeModal(modal.id);
            }
        });
    });

    // Initialize modal overlays
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                const modal = this.closest('.modal');
                if (modal) {
                    closeModal(modal.id);
                }
            }
        });
    });
}

// --- QUICK ACTIONS SETUP ---
function setupQuickActions() {
    const quickActionsGrid = document.querySelector('.quick-actions-grid');
    if (quickActionsGrid) {
        quickActionsGrid.addEventListener('click', function(e) {
            const actionBtn = e.target.closest('.quick-action-btn');
            if (actionBtn) {
                const action = actionBtn.dataset.action;
                handleQuickAction(action);
            }
        });
    }
}

// --- QUICK ACTION HANDLERS ---
function handleQuickAction(action) {
    closeModal('quick-actions-modal');
    
    switch(action) {
        case 'add-pet':
            openAddPetModal();
            break;
        case 'add-appointment':
            openAddAppointmentModal();
            break;
        case 'add-health-record':
            openAddHealthRecordModal();
            break;
        case 'add-vaccination':
            openAddVaccinationModal();
            break;
        case 'payment':
            openPaymentModal();
            break;
        case 'adoption':
            openAddAdoptionModal();
            break;
        case 'telemedicine':
            openTelemedicineModal();
            break;
        case 'prescription':
            openPrescriptionModal();
            break;
        default:
            showToast(`Action "${action}" not implemented yet`, 'warning');
    }
}

// --- MODAL HANDLERS ---
function setupModalHandlers() {
    // Setup form submissions
    setupFormHandler('add-pet-form', handleAddPet);
    setupFormHandler('add-appointment-form', handleAddAppointment);
    setupFormHandler('add-health-record-form', handleAddHealthRecord);
    setupFormHandler('add-vaccination-form', handleAddVaccination);
    setupFormHandler('telemedicine-form', handleTelemedicineSession);
    setupFormHandler('prescription-form', handlePrescription);
    setupFormHandler('add-user-form', handleAddUser);
}

function setupFormHandler(formId, handler) {
    const form = document.getElementById(formId);
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            handler(this);
        });
    }
}

// --- MODAL FUNCTIONS ---
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        modal.querySelector('.modal-overlay').classList.add('active');
        
        // Trap focus
        trapFocus(modal);
        
        // Load dynamic content if needed
        loadModalContent(modalId);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.querySelector('.modal-overlay').classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
        
        // Reset form if exists
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
    }
}

// --- FOCUS TRAPPING ---
function trapFocus(modal) {
    const focusableEls = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    const firstEl = focusableEls[0];
    const lastEl = focusableEls[focusableEls.length - 1];
    
    modal.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            if (e.shiftKey) {
                if (document.activeElement === firstEl) {
                    e.preventDefault();
                    lastEl.focus();
                }
            } else {
                if (document.activeElement === lastEl) {
                    e.preventDefault();
                    firstEl.focus();
                }
            }
        }
        if (e.key === 'Escape') {
            closeModal(modal.id);
        }
    });
    
    setTimeout(() => firstEl && firstEl.focus(), 100);
}

// --- MODAL CONTENT LOADING ---
function loadModalContent(modalId) {
    switch(modalId) {
        case 'add-pet-modal':
            // Pet modal is static, no dynamic loading needed
            break;
        case 'add-appointment-modal':
            loadPetsForAppointment();
            break;
        case 'add-health-record-modal':
            loadPetsForHealthRecord();
            break;
        case 'add-vaccination-modal':
            loadPetsForVaccination();
            break;
        case 'telemedicine-modal':
            loadPatientsForTelemedicine();
            break;
        case 'prescription-modal':
            loadPatientsForPrescription();
            break;
    }
}

// --- DYNAMIC CONTENT LOADING ---
async function loadPetsForAppointment() {
    const petSelect = document.getElementById('appointment-pet');
    if (!petSelect) return;
    
    try {
        // Simulate API call
        const pets = await getCurrentUserPets();
        petSelect.innerHTML = '<option value="">Select pet</option>';
        
        pets.forEach(pet => {
            const option = document.createElement('option');
            option.value = pet.id;
            option.textContent = pet.name;
            petSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Error loading pets:', error);
        showToast('Failed to load pets', 'error');
    }
}

async function loadPetsForHealthRecord() {
    const petSelect = document.getElementById('health-pet');
    if (!petSelect) return;
    
    try {
        const pets = await getCurrentUserPets();
        petSelect.innerHTML = '<option value="">Select pet</option>';
        
        pets.forEach(pet => {
            const option = document.createElement('option');
            option.value = pet.id;
            option.textContent = pet.name;
            petSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Error loading pets:', error);
        showToast('Failed to load pets', 'error');
    }
}

async function loadPetsForVaccination() {
    const petSelect = document.getElementById('vaccination-pet');
    if (!petSelect) return;
    
    try {
        const pets = await getCurrentUserPets();
        petSelect.innerHTML = '<option value="">Select pet</option>';
        
        pets.forEach(pet => {
            const option = document.createElement('option');
            option.value = pet.id;
            option.textContent = pet.name;
            petSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Error loading pets:', error);
        showToast('Failed to load pets', 'error');
    }
}

async function loadPatientsForTelemedicine() {
    const patientSelect = document.getElementById('telemedicine-pet');
    if (!patientSelect) return;
    
    try {
        // For vets, load all patients
        const patients = await getAllPatients();
        patientSelect.innerHTML = '<option value="">Select patient</option>';
        
        patients.forEach(patient => {
            const option = document.createElement('option');
            option.value = patient.id;
            option.textContent = `${patient.name} (${patient.owner})`;
            option.dataset.owner = patient.owner;
            patientSelect.appendChild(option);
        });
        
        // Update owner field when patient is selected
        patientSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const ownerField = document.getElementById('telemedicine-owner');
            if (ownerField && selectedOption.dataset.owner) {
                ownerField.value = selectedOption.dataset.owner;
            }
        });
    } catch (error) {
        console.error('Error loading patients:', error);
        showToast('Failed to load patients', 'error');
    }
}

async function loadPatientsForPrescription() {
    const patientSelect = document.getElementById('prescription-pet');
    if (!patientSelect) return;
    
    try {
        const patients = await getAllPatients();
        patientSelect.innerHTML = '<option value="">Select patient</option>';
        
        patients.forEach(patient => {
            const option = document.createElement('option');
            option.value = patient.id;
            option.textContent = `${patient.name} (${patient.owner})`;
            patientSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Error loading patients:', error);
        showToast('Failed to load patients', 'error');
    }
}

// --- FORM HANDLERS ---
async function handleAddPet(form) {
    const formData = new FormData(form);
    
    try {
        showLoadingState(form);
        
        // Simulate API call
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        const petData = {
            name: formData.get('name'),
            type: formData.get('type'),
            breed: formData.get('breed'),
            age: formData.get('age'),
            weight: formData.get('weight'),
            color: formData.get('color'),
            notes: formData.get('notes')
        };
        
        // Add to local storage for demo
        const pets = JSON.parse(localStorage.getItem('pets') || '[]');
        pets.push({
            id: Date.now(),
            ...petData,
            status: 'Active'
        });
        localStorage.setItem('pets', JSON.stringify(pets));
        
        showToast('Pet added successfully!', 'success');
        closeModal('add-pet-modal');
        
        // Refresh dashboard if on pets section
        if (currentUser && document.getElementById('owner-pets').classList.contains('active')) {
            loadPetsContent(currentUser.role);
        }
        
    } catch (error) {
        console.error('Error adding pet:', error);
        showToast('Failed to add pet', 'error');
    } finally {
        hideLoadingState(form);
    }
}

async function handleAddAppointment(form) {
    const formData = new FormData(form);
    
    try {
        showLoadingState(form);
        
        // Simulate API call
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        const appointmentData = {
            pet_id: formData.get('pet_id'),
            date: formData.get('date'),
            time: formData.get('time'),
            purpose: formData.get('purpose'),
            notes: formData.get('notes'),
            status: 'Scheduled'
        };
        
        // Add to local storage for demo
        const appointments = JSON.parse(localStorage.getItem('appointments') || '[]');
        appointments.push({
            id: Date.now(),
            ...appointmentData
        });
        localStorage.setItem('appointments', JSON.stringify(appointments));
        
        showToast('Appointment booked successfully!', 'success');
        closeModal('add-appointment-modal');
        
        // Refresh dashboard if on appointments section
        if (currentUser && document.getElementById('owner-appointments').classList.contains('active')) {
            loadAppointmentsContent(currentUser.role);
        }
        
    } catch (error) {
        console.error('Error booking appointment:', error);
        showToast('Failed to book appointment', 'error');
    } finally {
        hideLoadingState(form);
    }
}

async function handleAddHealthRecord(form) {
    const formData = new FormData(form);
    
    try {
        showLoadingState(form);
        
        // Simulate API call
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        const healthData = {
            pet_id: formData.get('pet_id'),
            date: formData.get('date'),
            type: formData.get('type'),
            description: formData.get('description'),
            vet: formData.get('vet'),
            cost: formData.get('cost')
        };
        
        // Add to local storage for demo
        const healthRecords = JSON.parse(localStorage.getItem('healthRecords') || '[]');
        healthRecords.push({
            id: Date.now(),
            ...healthData
        });
        localStorage.setItem('healthRecords', JSON.stringify(healthRecords));
        
        showToast('Health record added successfully!', 'success');
        closeModal('add-health-record-modal');
        
        // Refresh dashboard if on health section
        if (currentUser && document.getElementById('owner-health').classList.contains('active')) {
            loadHealthContent(currentUser.role);
        }
        
    } catch (error) {
        console.error('Error adding health record:', error);
        showToast('Failed to add health record', 'error');
    } finally {
        hideLoadingState(form);
    }
}

async function handleAddVaccination(form) {
    const formData = new FormData(form);
    
    try {
        showLoadingState(form);
        
        // Simulate API call
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        const vaccinationData = {
            pet_id: formData.get('pet_id'),
            vaccine_type: formData.get('vaccine_type'),
            date: formData.get('date'),
            next_due: formData.get('next_due'),
            notes: formData.get('notes'),
            status: 'Scheduled'
        };
        
        // Add to local storage for demo
        const vaccinations = JSON.parse(localStorage.getItem('vaccinations') || '[]');
        vaccinations.push({
            id: Date.now(),
            ...vaccinationData
        });
        localStorage.setItem('vaccinations', JSON.stringify(vaccinations));
        
        showToast('Vaccination scheduled successfully!', 'success');
        closeModal('add-vaccination-modal');
        
        // Refresh dashboard if on vaccinations section
        if (currentUser && document.getElementById('owner-vaccinations').classList.contains('active')) {
            loadVaccinationsContent(currentUser.role);
        }
        
    } catch (error) {
        console.error('Error scheduling vaccination:', error);
        showToast('Failed to schedule vaccination', 'error');
    } finally {
        hideLoadingState(form);
    }
}

async function handleTelemedicineSession(form) {
    const formData = new FormData(form);
    
    try {
        showLoadingState(form);
        
        // Simulate API call
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        const sessionData = {
            pet_id: formData.get('pet_id'),
            owner: formData.get('owner'),
            reason: formData.get('reason'),
            duration: formData.get('duration'),
            status: 'Active'
        };
        
        showToast('Telemedicine session started!', 'success');
        closeModal('telemedicine-modal');
        
        // Here you would typically redirect to video call interface
        showToast('Redirecting to video call...', 'info');
        
    } catch (error) {
        console.error('Error starting telemedicine session:', error);
        showToast('Failed to start session', 'error');
    } finally {
        hideLoadingState(form);
    }
}

async function handlePrescription(form) {
    const formData = new FormData(form);
    
    try {
        showLoadingState(form);
        
        // Simulate API call
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        const prescriptionData = {
            pet_id: formData.get('pet_id'),
            medication: formData.get('medication'),
            dosage: formData.get('dosage'),
            frequency: formData.get('frequency'),
            duration: formData.get('duration'),
            notes: formData.get('notes'),
            status: 'Active'
        };
        
        // Add to local storage for demo
        const prescriptions = JSON.parse(localStorage.getItem('prescriptions') || '[]');
        prescriptions.push({
            id: Date.now(),
            ...prescriptionData
        });
        localStorage.setItem('prescriptions', JSON.stringify(prescriptions));
        
        showToast('Prescription created successfully!', 'success');
        closeModal('prescription-modal');
        
    } catch (error) {
        console.error('Error creating prescription:', error);
        showToast('Failed to create prescription', 'error');
    } finally {
        hideLoadingState(form);
    }
}

async function handleAddUser(form) {
    const formData = new FormData(form);
    
    try {
        showLoadingState(form);
        
        // Simulate API call
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        const userData = {
            name: formData.get('name'),
            email: formData.get('email'),
            role: formData.get('role'),
            phone: formData.get('phone'),
            address: formData.get('address'),
            status: 'Active'
        };
        
        // Add to local storage for demo
        const users = JSON.parse(localStorage.getItem('users') || '[]');
        users.push({
            id: Date.now(),
            ...userData
        });
        localStorage.setItem('users', JSON.stringify(users));
        
        showToast('User added successfully!', 'success');
        closeModal('add-user-modal');
        
        // Refresh dashboard if on users section
        if (currentUser && document.getElementById('admin-users').classList.contains('active')) {
            loadUsersContent(currentUser.role);
        }
        
    } catch (error) {
        console.error('Error adding user:', error);
        showToast('Failed to add user', 'error');
    } finally {
        hideLoadingState(form);
    }
}

// --- UTILITY FUNCTIONS ---
function showLoadingState(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="loading-spinner"></span> Processing...';
    }
}

function hideLoadingState(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = submitBtn.dataset.originalText || 'Submit';
    }
}

function showToast(message, type = 'info') {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fas fa-${getToastIcon(type)}"></i>
            <span>${message}</span>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add to toast container
    const container = document.getElementById('toast-container') || document.body;
    container.appendChild(toast);
    
    // Show toast
    setTimeout(() => toast.classList.add('show'), 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

function getToastIcon(type) {
    switch(type) {
        case 'success': return 'check-circle';
        case 'error': return 'exclamation-circle';
        case 'warning': return 'exclamation-triangle';
        case 'info': return 'info-circle';
        default: return 'info-circle';
    }
}

// --- MOBILE NAVIGATION ---
function setupMobileNavigation() {
    const mobileToggle = document.getElementById('mobile-nav-toggle');
    const sidebar = document.querySelector('.dashboard-sidebar');
    
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show-mobile');
        });
    }
    
    // Close sidebar when clicking outside
    document.addEventListener('click', function(e) {
        if (sidebar && sidebar.classList.contains('show-mobile')) {
            if (!sidebar.contains(e.target) && !mobileToggle.contains(e.target)) {
                sidebar.classList.remove('show-mobile');
            }
        }
    });
}

// --- NOTIFICATION POLLING ---
function startNotificationPolling() {
    // Poll for notifications every 30 seconds
    setInterval(pollNotifications, 30000);
    
    // Initial poll
    pollNotifications();
}

async function pollNotifications() {
    const userId = localStorage.getItem('petCareUserId');
    if (!userId) return;
    
    try {
        // Simulate API call
        const response = await fetch('notifications.php?action=fetch&user_id=' + encodeURIComponent(userId));
        const data = await response.json();
        
        if (data.success && Array.isArray(data.data)) {
            updateNotificationBadge(data.data);
        }
    } catch (error) {
        // For demo purposes, simulate notifications
        const mockNotifications = generateMockNotifications();
        updateNotificationBadge(mockNotifications);
    }
}

function updateNotificationBadge(notifications) {
    const unread = notifications.filter(n => n.is_read == 0).length;
    
    // Update notification list in modal
    const notificationsList = document.getElementById('notifications-list');
    if (notificationsList) {
        if (notifications.length === 0) {
            notificationsList.innerHTML = '<li>No notifications.</li>';
        } else {
            notificationsList.innerHTML = notifications.map(n => 
                `<li${n.is_read == 0 ? ' style="font-weight:bold"' : ''}>
                    ${n.message} <small>${new Date(n.created_at).toLocaleString()}</small>
                </li>`
            ).join('');
        }
    }
    
    // Update FAB badge
    let badge = document.getElementById('notification-badge');
    if (!badge && unread > 0) {
        badge = document.createElement('span');
        badge.id = 'notification-badge';
        badge.style.cssText = `
            position: absolute;
            top: 6px;
            right: 6px;
            background: #e53935;
            color: #fff;
            border-radius: 50%;
            padding: 2px 7px;
            font-size: 0.8rem;
            z-index: 1200;
            min-width: 18px;
            text-align: center;
        `;
        const fab = document.getElementById('fab');
        if (fab) fab.appendChild(badge);
    }
    
    if (badge) {
        badge.style.display = unread > 0 ? 'inline-block' : 'none';
        badge.textContent = unread > 0 ? unread : '';
    }
    
    // Show toast for new notifications
    if (unread > lastNotificationCount) {
        showToast('You have new notifications!', 'info');
    }
    lastNotificationCount = unread;
}

function generateMockNotifications() {
    const notifications = [];
    const messages = [
        'Appointment reminder: Your pet has a checkup tomorrow',
        'Vaccination due: Max needs his annual rabies shot',
        'Payment received: Thank you for your recent payment',
        'New message from Dr. Smith regarding your pet\'s treatment',
        'System update: New features available in your dashboard'
    ];
    
    const count = Math.floor(Math.random() * 3) + 1;
    for (let i = 0; i < count; i++) {
        notifications.push({
            id: Date.now() + i,
            message: messages[Math.floor(Math.random() * messages.length)],
            is_read: Math.random() > 0.7 ? 0 : 1,
            created_at: new Date(Date.now() - Math.random() * 86400000).toISOString()
        });
    }
    
    return notifications;
}

// --- MOCK DATA FUNCTIONS ---
async function getCurrentUserPets() {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 500));
    
    const pets = JSON.parse(localStorage.getItem('pets') || '[]');
    return pets.filter(pet => pet.owner_id === currentUser?.id || !pet.owner_id);
}

async function getAllPatients() {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 500));
    
    const pets = JSON.parse(localStorage.getItem('pets') || '[]');
    return pets.map(pet => ({
        id: pet.id,
        name: pet.name,
        owner: pet.owner_name || 'Unknown Owner'
    }));
}

// --- MODAL OPENING FUNCTIONS ---
function openAddPetModal() {
    openModal('add-pet-modal');
}

function openAddAppointmentModal() {
    openModal('add-appointment-modal');
}

function openAddHealthRecordModal() {
    openModal('add-health-record-modal');
}

function openAddVaccinationModal() {
    openModal('add-vaccination-modal');
}

function openPaymentModal() {
    showToast('Payment integration coming soon!', 'info');
}

function openAddAdoptionModal() {
    showToast('Adoption feature coming soon!', 'info');
}

function openTelemedicineModal() {
    openModal('telemedicine-modal');
}

function openPrescriptionModal() {
    openModal('prescription-modal');
}

function openAddUserModal() {
    openModal('add-user-modal');
}

// --- EXPORT FUNCTIONS FOR GLOBAL ACCESS ---
window.openAddPetModal = openAddPetModal;
window.openAddAppointmentModal = openAddAppointmentModal;
window.openAddHealthRecordModal = openAddHealthRecordModal;
window.openAddVaccinationModal = openAddVaccinationModal;
window.openPaymentModal = openPaymentModal;
window.openAddAdoptionModal = openAddAdoptionModal;
window.openTelemedicineModal = openTelemedicineModal;
window.openPrescriptionModal = openPrescriptionModal;
window.openAddUserModal = openAddUserModal;
window.closeModal = closeModal;
window.showToast = showToast; 