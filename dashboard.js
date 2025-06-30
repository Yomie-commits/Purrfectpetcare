// Pet Care Management System - Functional Dashboard JavaScript
// This file contains all the functional dashboard code with real API integration

// Global variables
let currentUser = null;

// --- DASHBOARD DATA LOADING FUNCTIONS ---

// Load Pets Content
async function loadPetsContent(role) {
    const tbody = document.getElementById(`${role}-pets-tbody`);
    if (!tbody) return;

    try {
        // Show loading state
        tbody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

        // Fetch pets from backend
        const formData = new FormData();
        formData.append('action', 'get_pets');
        formData.append('user_id', currentUser.id);

        const response = await fetch('backend/api.php', {
            method: 'POST',
            body: await addCSRFTokenToBody(formData)
        });

        const result = await response.json();

        if (result.success && result.data) {
            const pets = result.data;
            tbody.innerHTML = '';

            if (pets.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No pets found. Add your first pet!</td></tr>';
                return;
            }

            pets.forEach(pet => {
                const statusClass = pet.status === 'Active' ? 'badge-success' : 'badge-danger';
                tbody.innerHTML += `
                    <tr>
                        <td>${pet.name}</td>
                        <td>${pet.type}</td>
                        <td>${pet.breed || 'N/A'}</td>
                        <td>${pet.age || 'N/A'}</td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick="editPet('${pet.id}', '${pet.name}', '${pet.type}', '${pet.breed || ''}', '${pet.age || ''}', '${pet.status}')" title="Edit Pet">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-info" onclick="viewPetDetails('${pet.id}')" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deletePet('${pet.id}', '${pet.name}')" title="Delete Pet">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load pets. Please try again.</td></tr>';
        }
    } catch (error) {
        console.error('Error loading pets:', error);
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading pets. Please check your connection.</td></tr>';
    }
}

// Load Appointments Content
async function loadAppointmentsContent(role) {
    const tbody = document.getElementById(`${role}-appointments-tbody`);
    if (!tbody) return;

    try {
        // Show loading state
        tbody.innerHTML = '<tr><td colspan="6" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

        // Fetch appointments from backend
        const formData = new FormData();
        formData.append('action', 'get_appointments');
        formData.append('user_id', currentUser.id);
        formData.append('role', role);

        const response = await fetch('backend/api.php', {
            method: 'POST',
            body: await addCSRFTokenToBody(formData)
        });

        const result = await response.json();

        if (result.success && result.data) {
            const appointments = result.data;
            tbody.innerHTML = '';

            if (appointments.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No appointments found.</td></tr>';
                return;
            }

            appointments.forEach(appointment => {
                const statusClass = appointment.status === 'Confirmed' ? 'badge-success' :
                    appointment.status === 'Scheduled' ? 'badge-primary' : 
                    appointment.status === 'Completed' ? 'badge-info' : 'badge-warning';

                if (role === 'owner') {
                    tbody.innerHTML += `
                        <tr>
                            <td>${appointment.date}</td>
                            <td>${appointment.time}</td>
                            <td>${appointment.pet_name || appointment.pet}</td>
                            <td>${appointment.purpose}</td>
                            <td><span class="badge ${statusClass}">${appointment.status}</span></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-primary" onclick="editAppointment('${appointment.id}', '${appointment.date}', '${appointment.time}', '${appointment.pet_name || appointment.pet}', '${appointment.purpose}', '${appointment.status}')" title="Edit Appointment">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="cancelAppointment('${appointment.id}', '${appointment.date}')" title="Cancel Appointment">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                } else if (role === 'vet') {
                    tbody.innerHTML += `
                        <tr>
                            <td>${appointment.date}</td>
                            <td>${appointment.time}</td>
                            <td>${appointment.pet_name || appointment.pet}</td>
                            <td>${appointment.owner_name || appointment.owner}</td>
                            <td>${appointment.purpose || appointment.reason}</td>
                            <td><span class="badge ${statusClass}">${appointment.status}</span></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-primary" onclick="startAppointment('${appointment.id}')" title="Start Appointment">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <button class="btn btn-sm btn-success" onclick="completeAppointment('${appointment.id}')" title="Complete Appointment">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-sm btn-info" onclick="viewAppointmentDetails('${appointment.id}')" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                } else if (role === 'admin') {
                    tbody.innerHTML += `
                        <tr>
                            <td>${appointment.date}</td>
                            <td>${appointment.time}</td>
                            <td>${appointment.pet_name || appointment.pet}</td>
                            <td>${appointment.owner_name || appointment.owner}</td>
                            <td>${appointment.vet_name || appointment.vet}</td>
                            <td><span class="badge ${statusClass}">${appointment.status}</span></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-primary" onclick="editAppointment('${appointment.id}', '${appointment.date}', '${appointment.time}', '${appointment.pet_name || appointment.pet}', '${appointment.owner_name || appointment.owner}', '${appointment.vet_name || appointment.vet}', '${appointment.status}')" title="Edit Appointment">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="cancelAppointment('${appointment.id}', '${appointment.date}')" title="Cancel Appointment">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <button class="btn btn-sm btn-info" onclick="viewAppointmentDetails('${appointment.id}')" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                }
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Failed to load appointments. Please try again.</td></tr>';
        }
    } catch (error) {
        console.error('Error loading appointments:', error);
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading appointments. Please check your connection.</td></tr>';
    }
}

// Load Health Records Content
async function loadHealthContent(role) {
    const tbody = document.getElementById(`${role}-health-tbody`);
    if (!tbody) return;

    try {
        // Show loading state
        tbody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

        // Fetch health records from backend
        const formData = new FormData();
        formData.append('action', 'get_health_records');
        formData.append('user_id', currentUser.id);

        const response = await fetch('backend/api.php', {
            method: 'POST',
            body: await addCSRFTokenToBody(formData)
        });

        const result = await response.json();

        if (result.success && result.data) {
            const records = result.data;
            tbody.innerHTML = '';

            if (records.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No health records found.</td></tr>';
                return;
            }

            records.forEach(record => {
                tbody.innerHTML += `
                    <tr>
                        <td>${record.pet_name || record.pet}</td>
                        <td>${record.type}</td>
                        <td>${record.details}</td>
                        <td>${record.date}</td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick="editHealthRecord('${record.id}', '${record.pet_name || record.pet}', '${record.type}', '${record.details}', '${record.date}')" title="Edit Record">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-info" onclick="viewHealthRecord('${record.id}')" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteHealthRecord('${record.id}', '${record.pet_name || record.pet}')" title="Delete Record">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load health records. Please try again.</td></tr>';
        }
    } catch (error) {
        console.error('Error loading health records:', error);
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading health records. Please check your connection.</td></tr>';
    }
}

// Load Vaccinations Content
async function loadVaccinationsContent(role) {
    const tbody = document.getElementById(`${role}-vaccinations-tbody`);
    if (!tbody) return;

    try {
        // Show loading state
        tbody.innerHTML = '<tr><td colspan="6" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

        // Fetch vaccinations from backend
        const formData = new FormData();
        formData.append('action', 'get_vaccinations');
        formData.append('user_id', currentUser.id);

        const response = await fetch('backend/api.php', {
            method: 'POST',
            body: await addCSRFTokenToBody(formData)
        });

        const result = await response.json();

        if (result.success && result.data) {
            const vaccinations = result.data;
            tbody.innerHTML = '';

            if (vaccinations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No vaccination records found.</td></tr>';
                return;
            }

            vaccinations.forEach(vaccination => {
                const statusClass = vaccination.status === 'Up to date' ? 'badge-success' :
                    vaccination.status === 'Due soon' ? 'badge-warning' : 'badge-danger';

                tbody.innerHTML += `
                    <tr>
                        <td>${vaccination.pet_name || vaccination.pet}</td>
                        <td>${vaccination.vaccine_name || vaccination.vaccine}</td>
                        <td>${vaccination.last_given}</td>
                        <td>${vaccination.next_due}</td>
                        <td><span class="badge ${statusClass}">${vaccination.status}</span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick="editVaccination('${vaccination.id}', '${vaccination.pet_name || vaccination.pet}', '${vaccination.vaccine_name || vaccination.vaccine}', '${vaccination.last_given}', '${vaccination.next_due}', '${vaccination.status}')" title="Edit Vaccination">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-success" onclick="recordVaccination('${vaccination.id}')" title="Record Vaccination">
                                    <i class="fas fa-syringe"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteVaccination('${vaccination.id}', '${vaccination.pet_name || vaccination.pet}')" title="Delete Vaccination">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Failed to load vaccinations. Please try again.</td></tr>';
        }
    } catch (error) {
        console.error('Error loading vaccinations:', error);
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading vaccinations. Please check your connection.</td></tr>';
    }
}

// Load Payments Content
async function loadPaymentsContent(role) {
    const tbody = document.getElementById(`${role}-payments-tbody`);
    if (!tbody) return;

    try {
        // Show loading state
        tbody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

        // Fetch payments from backend
        const formData = new FormData();
        formData.append('action', 'get_payments');
        formData.append('user_id', currentUser.id);

        const response = await fetch('backend/api.php', {
            method: 'POST',
            body: await addCSRFTokenToBody(formData)
        });

        const result = await response.json();

        if (result.success && result.data) {
            const payments = result.data;
            tbody.innerHTML = '';

            if (payments.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No payment records found.</td></tr>';
                return;
            }

            payments.forEach(payment => {
                const statusClass = payment.status === 'Completed' ? 'badge-success' :
                    payment.status === 'Pending' ? 'badge-warning' : 'badge-danger';

                tbody.innerHTML += `
                    <tr>
                        <td>${payment.date}</td>
                        <td>KSh ${parseFloat(payment.amount).toLocaleString()}</td>
                        <td>${payment.method}</td>
                        <td><span class="badge ${statusClass}">${payment.status}</span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-info" onclick="viewPayment('${payment.id}')" title="View Payment">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-success" onclick="downloadReceipt('${payment.id}')" title="Download Receipt">
                                    <i class="fas fa-download"></i>
                                </button>
                                ${role === 'admin' ? `<button class="btn btn-sm btn-danger" onclick="deletePayment('${payment.id}')" title="Delete Payment">
                                    <i class="fas fa-trash"></i>
                                </button>` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load payments. Please try again.</td></tr>';
        }
    } catch (error) {
        console.error('Error loading payments:', error);
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading payments. Please check your connection.</td></tr>';
    }
}

// Load Adoption Content
async function loadAdoptionContent(role) {
    const tbody = document.getElementById(`${role}-adoption-tbody`);
    if (!tbody) return;

    try {
        // Show loading state
        tbody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

        // Fetch adoptions from backend
        const formData = new FormData();
        formData.append('action', 'get_adoptions');

        const response = await fetch('backend/api.php', {
            method: 'POST',
            body: await addCSRFTokenToBody(formData)
        });

        const result = await response.json();

        if (result.success && result.data) {
            const adoptions = result.data;
            tbody.innerHTML = '';

            if (adoptions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No adoption listings found.</td></tr>';
                return;
            }

            adoptions.forEach(adoption => {
                const statusClass = adoption.status === 'Available' ? 'badge-success' :
                    adoption.status === 'Pending' ? 'badge-warning' : 'badge-info';

                tbody.innerHTML += `
                    <tr>
                        <td>${adoption.pet_name}</td>
                        <td>${adoption.description}</td>
                        <td><span class="badge ${statusClass}">${adoption.status}</span></td>
                        <td>${adoption.date_posted}</td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick="editAdoption('${adoption.id}', '${adoption.pet_name}', '${adoption.description}', '${adoption.status}')" title="Edit Adoption">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-success" onclick="approveAdoption('${adoption.id}')" title="Approve Adoption">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteAdoption('${adoption.id}', '${adoption.pet_name}')" title="Delete Adoption">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load adoptions. Please try again.</td></tr>';
        }
    } catch (error) {
        console.error('Error loading adoptions:', error);
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading adoptions. Please check your connection.</td></tr>';
    }
}

// Load Lost & Found Content
async function loadLostFoundContent(role) {
    const tbody = document.getElementById(`${role}-lostfound-tbody`);
    if (!tbody) return;

    try {
        // Show loading state
        tbody.innerHTML = '<tr><td colspan="6" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

        // Fetch lost & found from backend
        const formData = new FormData();
        formData.append('action', 'get_lost_found');

        const response = await fetch('backend/api.php', {
            method: 'POST',
            body: await addCSRFTokenToBody(formData)
        });

        const result = await response.json();

        if (result.success && result.data) {
            const reports = result.data;
            tbody.innerHTML = '';

            if (reports.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No lost & found reports found.</td></tr>';
                return;
            }

            reports.forEach(report => {
                const statusClass = report.status === 'Found' ? 'badge-success' :
                    report.status === 'Searching' ? 'badge-warning' : 'badge-info';
                const typeClass = report.type === 'Lost' ? 'text-danger' : 'text-success';

                tbody.innerHTML += `
                    <tr>
                        <td><span class="${typeClass}"><i class="fas fa-${report.type === 'Lost' ? 'search' : 'hand-holding-heart'}"></i> ${report.type}</span></td>
                        <td>${report.pet}</td>
                        <td>${report.location}</td>
                        <td>${report.date}</td>
                        <td><span class="badge ${statusClass}">${report.status}</span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick="editLostFound('${report.id}', '${report.type}', '${report.pet}', '${report.location}', '${report.date}', '${report.status}')" title="Edit Report">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-success" onclick="markFound('${report.id}')" title="Mark as Found">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteLostFound('${report.id}', '${report.pet}')" title="Delete Report">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Failed to load lost & found reports. Please try again.</td></tr>';
        }
    } catch (error) {
        console.error('Error loading lost & found reports:', error);
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading lost & found reports. Please check your connection.</td></tr>';
    }
}

// --- CRUD OPERATIONS FOR PETS ---

async function addPet() {
    const form = document.getElementById('add-pet-form');
    const formData = new FormData(form);
    formData.append('action', 'add_pet');
    formData.append('owner_id', currentUser.id);

    try {
        const response = await fetch('backend/api.php', {
            method: 'POST',
            body: await addCSRFTokenToBody(formData)
        });

        const result = await response.json();

        if (result.success) {
            showToast('Pet added successfully!', 'success');
            closeModal('add-pet-modal');
            form.reset();
            loadPetsContent(currentUser.role);
        } else {
            showToast(result.error || 'Failed to add pet', 'error');
        }
    } catch (error) {
        console.error('Error adding pet:', error);
        showToast('Network error. Please try again.', 'error');
    }
}

async function editPet(id, name, type, breed, age, status) {
    // Populate edit form
    document.getElementById('edit-pet-id').value = id;
    document.getElementById('edit-pet-name').value = name;
    document.getElementById('edit-pet-type').value = type;
    document.getElementById('edit-pet-breed').value = breed;
    document.getElementById('edit-pet-age').value = age;
    document.getElementById('edit-pet-status').value = status;

    openModal('edit-pet-modal');
}

async function updatePet() {
    const form = document.getElementById('edit-pet-form');
    const formData = new FormData(form);
    formData.append('action', 'update_pet');

    try {
        const response = await fetch('backend/api.php', {
            method: 'POST',
            body: await addCSRFTokenToBody(formData)
        });

        const result = await response.json();

        if (result.success) {
            showToast('Pet updated successfully!', 'success');
            closeModal('edit-pet-modal');
            loadPetsContent(currentUser.role);
        } else {
            showToast(result.error || 'Failed to update pet', 'error');
        }
    } catch (error) {
        console.error('Error updating pet:', error);
        showToast('Network error. Please try again.', 'error');
    }
}

async function deletePet(id, name) {
    // Show confirmation modal
    document.getElementById('confirmation-title').textContent = 'Delete Pet';
    document.getElementById('confirmation-message').textContent = `Are you sure you want to delete ${name}? This action cannot be undone.`;
    document.getElementById('confirmation-btn-text').textContent = 'Delete Pet';
    
    openModal('confirmation-modal');
    
    // Set up confirmation action
    document.getElementById('confirmation-confirm-btn').onclick = async function() {
        try {
            const formData = new FormData();
            formData.append('action', 'delete_pet');
            formData.append('id', id);

            const response = await fetch('backend/api.php', {
                method: 'POST',
                body: await addCSRFTokenToBody(formData)
            });

            const result = await response.json();

            if (result.success) {
                showToast('Pet deleted successfully!', 'success');
                closeModal('confirmation-modal');
                loadPetsContent(currentUser.role);
            } else {
                showToast(result.error || 'Failed to delete pet', 'error');
            }
        } catch (error) {
            console.error('Error deleting pet:', error);
            showToast('Network error. Please try again.', 'error');
        }
    };
}

async function viewPetDetails(id) {
    try {
        const formData = new FormData();
        formData.append('action', 'get_pet_details');
        formData.append('id', id);

        const response = await fetch('backend/api.php', {
            method: 'POST',
            body: await addCSRFTokenToBody(formData)
        });

        const result = await response.json();

        if (result.success && result.data) {
            const pet = result.data;
            
            // Populate view modal
            document.getElementById('view-pet-name').textContent = pet.name;
            document.getElementById('view-pet-type').textContent = pet.type;
            document.getElementById('view-pet-breed').textContent = pet.breed || 'N/A';
            document.getElementById('view-pet-age').textContent = pet.age || 'N/A';
            document.getElementById('view-pet-owner').textContent = pet.owner || 'N/A';
            document.getElementById('view-pet-status').textContent = pet.status;

            // Load medical history
            const medicalHistoryContainer = document.getElementById('view-pet-medical-history');
            if (pet.medical_records && pet.medical_records.length > 0) {
                medicalHistoryContainer.innerHTML = pet.medical_records.map(record => `
                    <div class="mb-2 p-2 border rounded">
                        <strong>${record.type}</strong> - ${record.date}<br>
                        <small>${record.details}</small>
                    </div>
                `).join('');
            } else {
                medicalHistoryContainer.innerHTML = '<p class="text-muted">No medical records found.</p>';
            }

            openModal('view-pet-modal');
        } else {
            showToast(result.error || 'Failed to load pet details', 'error');
        }
    } catch (error) {
        console.error('Error loading pet details:', error);
        showToast('Network error. Please try again.', 'error');
    }
}

// --- CRUD OPERATIONS FOR APPOINTMENTS ---

async function addAppointment() {
    const form = document.getElementById('add-appointment-form');
    const formData = new FormData(form);
    formData.append('action', 'add_appointment');
    formData.append('user_id', currentUser.id);

    try {
        const response = await fetch('backend/api.php', {
            method: 'POST',
            body: await addCSRFTokenToBody(formData)
        });

        const result = await response.json();

        if (result.success) {
            showToast('Appointment booked successfully!', 'success');
            closeModal('add-appointment-modal');
            form.reset();
            loadAppointmentsContent(currentUser.role);
        } else {
            showToast(result.error || 'Failed to book appointment', 'error');
        }
    } catch (error) {
        console.error('Error booking appointment:', error);
        showToast('Network error. Please try again.', 'error');
    }
}

async function editAppointment(id, date, time, pet, purpose, status, owner = '', vet = '') {
    // Populate edit form
    document.getElementById('edit-appointment-id').value = id;
    document.getElementById('edit-appointment-date').value = date;
    document.getElementById('edit-appointment-time').value = time;
    document.getElementById('edit-appointment-pet').value = pet;
    document.getElementById('edit-appointment-purpose').value = purpose;
    document.getElementById('edit-appointment-status').value = status;
    if (owner) document.getElementById('edit-appointment-owner').value = owner;
    if (vet) document.getElementById('edit-appointment-vet').value = vet;

    openModal('edit-appointment-modal');
}

async function updateAppointment() {
    const form = document.getElementById('edit-appointment-form');
    const formData = new FormData(form);
    formData.append('action', 'update_appointment');

    try {
        const response = await fetch('backend/api.php', {
            method: 'POST',
            body: await addCSRFTokenToBody(formData)
        });

        const result = await response.json();

        if (result.success) {
            showToast('Appointment updated successfully!', 'success');
            closeModal('edit-appointment-modal');
            loadAppointmentsContent(currentUser.role);
        } else {
            showToast(result.error || 'Failed to update appointment', 'error');
        }
    } catch (error) {
        console.error('Error updating appointment:', error);
        showToast('Network error. Please try again.', 'error');
    }
}

async function cancelAppointment(id, date) {
    // Show confirmation modal
    document.getElementById('confirmation-title').textContent = 'Cancel Appointment';
    document.getElementById('confirmation-message').textContent = `Are you sure you want to cancel the appointment on ${date}?`;
    document.getElementById('confirmation-btn-text').textContent = 'Cancel Appointment';
    
    openModal('confirmation-modal');
    
    // Set up confirmation action
    document.getElementById('confirmation-confirm-btn').onclick = async function() {
        try {
            const formData = new FormData();
            formData.append('action', 'cancel_appointment');
            formData.append('id', id);

            const response = await fetch('backend/api.php', {
                method: 'POST',
                body: await addCSRFTokenToBody(formData)
            });

            const result = await response.json();

            if (result.success) {
                showToast('Appointment cancelled successfully!', 'success');
                closeModal('confirmation-modal');
                loadAppointmentsContent(currentUser.role);
            } else {
                showToast(result.error || 'Failed to cancel appointment', 'error');
            }
        } catch (error) {
            console.error('Error cancelling appointment:', error);
            showToast('Network error. Please try again.', 'error');
        }
    };
}

async function startAppointment(id) {
    try {
        const formData = new FormData();
        formData.append('action', 'start_appointment');
        formData.append('id', id);

        const response = await fetch('backend/api.php', {
            method: 'POST',
            body: await addCSRFTokenToBody(formData)
        });

        const result = await response.json();

        if (result.success) {
            showToast('Appointment started!', 'success');
            loadAppointmentsContent(currentUser.role);
        } else {
            showToast(result.error || 'Failed to start appointment', 'error');
        }
    } catch (error) {
        console.error('Error starting appointment:', error);
        showToast('Network error. Please try again.', 'error');
    }
}

async function completeAppointment(id) {
    try {
        const formData = new FormData();
        formData.append('action', 'complete_appointment');
        formData.append('id', id);

        const response = await fetch('backend/api.php', {
            method: 'POST',
            body: await addCSRFTokenToBody(formData)
        });

        const result = await response.json();

        if (result.success) {
            showToast('Appointment completed!', 'success');
            loadAppointmentsContent(currentUser.role);
        } else {
            showToast(result.error || 'Failed to complete appointment', 'error');
        }
    } catch (error) {
        console.error('Error completing appointment:', error);
        showToast('Network error. Please try again.', 'error');
    }
}

// --- UTILITY FUNCTIONS ---

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (toast) {
        toast.textContent = message;
        toast.className = 'toast ' + type;
        toast.classList.add('show');

        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
}

// --- MODAL ACCESSIBILITY & FEEDBACK ENHANCEMENTS ---
function trapFocus(modal) {
    const focusableEls = modal.querySelectorAll('a, button, textarea, input, select, [tabindex]:not([tabindex="-1"])');
    const firstFocusableEl = focusableEls[0];
    const lastFocusableEl = focusableEls[focusableEls.length - 1];
    modal.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            if (e.shiftKey) {
                if (document.activeElement === firstFocusableEl) {
                    e.preventDefault();
                    lastFocusableEl.focus();
                }
            } else {
                if (document.activeElement === lastFocusableEl) {
                    e.preventDefault();
                    firstFocusableEl.focus();
                }
            }
        }
        if (e.key === 'Escape') {
            closeModal(modal.id);
        }
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        // Focus first input if exists
        const firstInput = modal.querySelector('input, select, textarea, button');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
        trapFocus(modal);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
        // Reset form if exists
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
    }
}

// Overlay click-to-close
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        const modal = e.target.closest('.modal');
        if (modal) closeModal(modal.id);
    }
});

// --- FORM VALIDATION & FEEDBACK ---
function showFormError(formId, message) {
    const errorDiv = document.getElementById(formId + '-error');
    if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    }
}
function clearFormError(formId) {
    const errorDiv = document.getElementById(formId + '-error');
    if (errorDiv) errorDiv.style.display = 'none';
}
function showFormSuccess(formId, message) {
    const successDiv = document.getElementById(formId + '-success');
    if (successDiv) {
        successDiv.textContent = message;
        successDiv.style.display = 'block';
    }
}
function clearFormSuccess(formId) {
    const successDiv = document.getElementById(formId + '-success');
    if (successDiv) successDiv.style.display = 'none';
}
// Example: Add validation to add-pet-form
const addPetForm = document.getElementById('add-pet-form');
if (addPetForm) {
    addPetForm.addEventListener('submit', function(e) {
        e.preventDefault();
        clearFormError('add-pet-form');
        clearFormSuccess('add-pet-form');
        let valid = true;
        if (!addPetForm.name.value) {
            showFormError('add-pet-name', 'Pet name is required.');
            valid = false;
        }
        if (!addPetForm.type.value) {
            showFormError('add-pet-type', 'Pet type is required.');
            valid = false;
        }
        if (!valid) return;
        document.getElementById('add-pet-loading').style.display = 'inline-block';
        // Simulate async submit
        setTimeout(() => {
            document.getElementById('add-pet-loading').style.display = 'none';
            showFormSuccess('add-pet-form', 'Pet added successfully!');
            addPetForm.reset();
        }, 1500);
    });
}
// Repeat similar validation for other forms...
// --- HAMBURGER MENU FIX ---
const mobileMenuBtn = document.getElementById('mobile-menu-btn');
const mobileMenu = document.getElementById('mobile-menu');
if (mobileMenuBtn && mobileMenu) {
    mobileMenuBtn.addEventListener('click', function() {
        mobileMenu.classList.toggle('active');
        mobileMenuBtn.setAttribute('aria-expanded', mobileMenu.classList.contains('active'));
    });
}

// --- DASHBOARD MODAL FUNCTIONS ---

function openAddPetModal() {
    // Reset form
    document.getElementById('add-pet-form').reset();
    openModal('add-pet-modal');
}

function openAddAppointmentModal() {
    // Reset form
    document.getElementById('add-appointment-form').reset();
    openModal('add-appointment-modal');
}

function openAddHealthRecordModal() {
    // Reset form
    document.getElementById('add-health-record-form').reset();
    openModal('add-health-record-modal');
}

function openAddVaccinationModal() {
    // Reset form
    document.getElementById('add-vaccination-form').reset();
    openModal('add-vaccination-modal');
}

function openPaymentModal() {
    // Reset form
    document.getElementById('payment-form').reset();
    openModal('payment-modal');
}

function openAddAdoptionModal() {
    // Reset form
    document.getElementById('add-adoption-form').reset();
    openModal('add-adoption-modal');
}

// --- RESPONSIVE FUNCTIONS ---

function setupResponsiveTables() {
    const tables = document.querySelectorAll('.dashboard-table');
    tables.forEach(table => {
        if (window.innerWidth <= 768) {
            setupMobileTable(table);
        } else {
            setupDesktopTable(table);
        }
    });
}

function setupMobileTable(table) {
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        row.addEventListener('click', function () {
            if (this.classList.contains('expanded')) {
                this.classList.remove('expanded');
            } else {
                // Close other expanded rows
                rows.forEach(r => r.classList.remove('expanded'));
                this.classList.add('expanded');
            }
        });

        // Add data attributes for mobile display
        const cells = row.querySelectorAll('td');
        const headers = table.querySelectorAll('th');
        cells.forEach((cell, index) => {
            if (headers[index]) {
                cell.setAttribute('data-label', headers[index].textContent);
            }
        });
    });
}

function setupDesktopTable(table) {
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        row.classList.remove('expanded');
        row.style.cursor = 'default';
    });
}

// --- INITIALIZATION ---

document.addEventListener('DOMContentLoaded', function() {
    // Check if user is logged in
    const loggedInUserId = localStorage.getItem('petCareUserId');
    const userRole = localStorage.getItem('petCareUserRole');
    
    if (loggedInUserId && userRole) {
        currentUser = {
            id: loggedInUserId,
            role: userRole,
            name: localStorage.getItem('petCareUserName') || 'User'
        };
    }

    // Setup responsive features
    setupResponsiveTables();
    
    // Handle window resize
    window.addEventListener('resize', setupResponsiveTables);

    // Load admin analytics
    if (userRole === 'admin') {
        loadAdminAnalytics();
    }
});

// Export functions for use in HTML
window.loadPetsContent = loadPetsContent;
window.loadAppointmentsContent = loadAppointmentsContent;
window.loadHealthContent = loadHealthContent;
window.loadVaccinationsContent = loadVaccinationsContent;
window.loadPaymentsContent = loadPaymentsContent;
window.loadAdoptionContent = loadAdoptionContent;
window.loadLostFoundContent = loadLostFoundContent;
window.addPet = addPet;
window.editPet = editPet;
window.updatePet = updatePet;
window.deletePet = deletePet;
window.viewPetDetails = viewPetDetails;
window.addAppointment = addAppointment;
window.editAppointment = editAppointment;
window.updateAppointment = updateAppointment;
window.cancelAppointment = cancelAppointment;
window.startAppointment = startAppointment;
window.completeAppointment = completeAppointment;
window.showToast = showToast;
window.openModal = openModal;
window.closeModal = closeModal;
window.openAddPetModal = openAddPetModal;
window.openAddAppointmentModal = openAddAppointmentModal;
window.openAddHealthRecordModal = openAddHealthRecordModal;
window.openAddVaccinationModal = openAddVaccinationModal;
window.openPaymentModal = openPaymentModal;
window.openAddAdoptionModal = openAddAdoptionModal;

// --- ADMIN ANALYTICS ---
async function loadAdminAnalytics() {
    // Appointments by Month
    const appointmentsChart = document.getElementById('appointmentsChart').getContext('2d');
    const usersChart = document.getElementById('usersChart').getContext('2d');
    let appointmentsData = [];
    let userDistData = [];
    try {
        const res1 = await fetch('backend/api.php', { method: 'POST', body: new URLSearchParams({ action: 'get_appointments_analytics' }) });
        const data1 = await res1.json();
        if (data1.success && Array.isArray(data1.data)) {
            appointmentsData = data1.data.reverse();
        }
        const res2 = await fetch('backend/api.php', { method: 'POST', body: new URLSearchParams({ action: 'get_user_distribution' }) });
        const data2 = await res2.json();
        if (data2.success && Array.isArray(data2.data)) {
            userDistData = data2.data;
        }
    } catch (e) {
        console.error('Failed to load analytics', e);
    }
    // Render Appointments Chart
    new Chart(appointmentsChart, {
        type: 'bar',
        data: {
            labels: appointmentsData.map(row => row.month),
            datasets: [{
                label: 'Appointments',
                data: appointmentsData.map(row => row.count),
                backgroundColor: '#1976d2',
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });
    // Render User Distribution Chart
    new Chart(usersChart, {
        type: 'pie',
        data: {
            labels: userDistData.map(row => row.role),
            datasets: [{
                data: userDistData.map(row => row.count),
                backgroundColor: ['#1976d2', '#43a047', '#ffa000'],
            }]
        },
        options: { responsive: true }
    });
}

function setupDashboard(role) {
    // ... existing code ...
    if (role === 'admin') {
        loadAdminAnalytics();
    }
    // ... existing code ...
}

// --- ADMIN FORUM MODERATION ---
async function loadAdminForumPosts() {
    const tbody = document.getElementById('admin-forum-table').querySelector('tbody');
    tbody.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';
    try {
        const res = await fetch('backend/api.php', { method: 'POST', body: new URLSearchParams({ action: 'get_forum_posts', status: 'Pending' }) });
        const data = await res.json();
        if (data.success && Array.isArray(data.data)) {
            tbody.innerHTML = '';
            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5">No pending posts.</td></tr>';
            } else {
                data.data.forEach(post => {
                    tbody.innerHTML += `<tr>
                        <td>${post.author}</td>
                        <td>${post.content}</td>
                        <td>${post.date}</td>
                        <td>${post.status}</td>
                        <td>
                            <button class="btn btn-success btn-sm" onclick="moderateForumPost('${post.author}', 'approve')">Approve</button>
                            <button class="btn btn-danger btn-sm" onclick="moderateForumPost('${post.author}', 'reject')">Reject</button>
                        </td>
                    </tr>`;
                });
            }
        } else {
            tbody.innerHTML = '<tr><td colspan="5">Failed to load posts.</td></tr>';
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5">Error loading posts.</td></tr>';
    }
}

window.moderateForumPost = async function(author, action) {
    const adminId = localStorage.getItem('petCareUserId');
    if (!adminId) return alert('Admin not logged in');
    const apiAction = action === 'approve' ? 'approve_post' : 'reject_post';
    try {
        const res = await fetch('backend/api.php', { method: 'POST', body: new URLSearchParams({ action: apiAction, author }) });
        const data = await res.json();
        if (data.success) {
            // Log to audit
            await fetch('backend/api.php', { method: 'POST', body: new URLSearchParams({ action: 'log_audit_action', admin_id: adminId, action: apiAction, details: `Forum post by ${author}` }) });
            showToast(`Post ${action}d.`, 'success');
            loadAdminForumPosts();
        } else {
            showToast(data.error || 'Failed to moderate post', 'error');
        }
    } catch (e) {
        showToast('Network error', 'error');
    }
}

// Call loadAdminForumPosts when admin-forum section is shown
function showDashboardSection(sectionId) {
    // ... existing code ...
    if (sectionId === 'admin-forum') {
        loadAdminForumPosts();
    }
    // ... existing code ...
}

// --- PET PHOTO UPLOAD ---
window.handlePetPhotoUpload = async function(petId, fileInputId, imgPreviewId) {
    const fileInput = document.getElementById(fileInputId);
    if (!fileInput || !fileInput.files.length) return;
    const formData = new FormData();
    formData.append('action', 'upload_pet_photo');
    formData.append('pet_id', petId);
    formData.append('photo', fileInput.files[0]);
    try {
        const res = await fetch('backend/api.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success && data.photo_url) {
            if (imgPreviewId) {
                document.getElementById(imgPreviewId).src = data.photo_url + '?t=' + Date.now();
            }
            showToast('Photo uploaded!', 'success');
        } else {
            showToast(data.error || 'Upload failed', 'error');
        }
    } catch (e) {
        showToast('Network error', 'error');
    }
}

// --- ADMIN NOTIFICATION MODAL ---
window.openNotificationModal = function() {
    document.getElementById('notification-modal').classList.add('active');
    setTimeout(() => document.getElementById('notification-email-to').focus(), 100);
};
window.closeNotificationModal = function() {
    document.getElementById('notification-modal').classList.remove('active');
};
window.sendEmailNotification = async function() {
    const to = document.getElementById('notification-email-to').value;
    const subject = document.getElementById('notification-email-subject').value;
    const message = document.getElementById('notification-email-message').value;
    const adminId = localStorage.getItem('petCareUserId');
    if (!to || !subject || !message) return showToast('All fields required', 'error');
    const formData = new URLSearchParams({ action: 'send_email_notification', to, subject, message, admin_id: adminId });
    try {
        const res = await fetch('backend/api.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            showToast('Email sent!', 'success');
            closeNotificationModal();
        } else {
            showToast(data.error || 'Failed to send email', 'error');
        }
    } catch (e) {
        showToast('Network error', 'error');
    }
};
window.sendSMSNotification = async function() {
    const to = document.getElementById('notification-sms-to').value;
    const message = document.getElementById('notification-sms-message').value;
    const adminId = localStorage.getItem('petCareUserId');
    if (!to || !message) return showToast('All fields required', 'error');
    const formData = new URLSearchParams({ action: 'send_sms_notification', to, message, admin_id: adminId });
    try {
        const res = await fetch('backend/api.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            showToast('SMS sent!', 'success');
            closeNotificationModal();
        } else {
            showToast(data.error || 'Failed to send SMS', 'error');
        }
    } catch (e) {
        showToast('Network error', 'error');
    }
};

// --- ADMIN AUDIT LOGS ---
window.openAuditLogModal = function() {
    document.getElementById('audit-log-modal').classList.add('active');
    loadAuditLogs();
};
window.closeAuditLogModal = function() {
    document.getElementById('audit-log-modal').classList.remove('active');
};
window.loadAuditLogs = async function() {
    const tbody = document.getElementById('audit-log-tbody');
    tbody.innerHTML = '<tr><td colspan="4">Loading...</td></tr>';
    try {
        const res = await fetch('backend/api.php', { method: 'POST', body: new URLSearchParams({ action: 'get_audit_logs' }) });
        const data = await res.json();
        if (data.success && Array.isArray(data.data)) {
            tbody.innerHTML = '';
            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4">No audit logs found.</td></tr>';
            } else {
                data.data.forEach(log => {
                    tbody.innerHTML += `<tr>
                        <td>${log.created_at}</td>
                        <td>${log.admin_name}</td>
                        <td>${log.action}</td>
                        <td>${log.details}</td>
                    </tr>`;
                });
            }
        } else {
            tbody.innerHTML = '<tr><td colspan="4">Failed to load logs.</td></tr>';
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="4">Error loading logs.</td></tr>';
    }
};

// Utility to fetch CSRF token
async function getCSRFToken() {
    const res = await fetch('backend/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_csrf_token' })
    });
    const data = await res.json();
    return data.token;
}

// Helper to add CSRF token to FormData or URLSearchParams
async function addCSRFTokenToBody(body) {
    const token = await getCSRFToken();
    if (body instanceof FormData || body instanceof URLSearchParams) {
        body.append('csrf_token', token);
        return body;
    } else if (typeof body === 'object') {
        body.csrf_token = token;
        return JSON.stringify(body);
    }
    return body;
} 