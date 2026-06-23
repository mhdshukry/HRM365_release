<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Fetch lists for filters
if ($currentUser['role'] === 'employee') {
    $departments = [];
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM employees WHERE id = ?");
    $stmt->execute([$currentUser['employee_id'] ?? 0]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($currentUser['role'] === 'manager') {
    $departments = [$currentUser['department'] ?? ''];
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM employees WHERE status = 'Active' AND department = ? ORDER BY first_name");
    $stmt->execute([$currentUser['department'] ?? '']);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
    $employees = $pdo->query("SELECT id, first_name, last_name FROM employees WHERE status = 'Active' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);
}

include '../../includes/header.php'; 
?>

<!-- FullCalendar Library -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<div class="page-header">
    <div>
        <h1 class="page-title">Calendar Overview</h1>
        <div class="page-subtitle">Integrated view of company holidays, approved employee leaves, and meetings.</div>
    </div>
</div>

<div class="card calendar-filter-card">
    <div class="calendar-filter-field">
        <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.85rem; font-weight: 500;">Event Type</label>
        <select id="filterType" class="form-control" style="width: 100%; padding: 0.6rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            <option value="all">All Events</option>
            <option value="meeting">Meetings Only</option>
            <option value="holiday">Holidays Only</option>
            <option value="leave">Leaves Only</option>
        </select>
    </div>
    <div class="calendar-filter-field">
        <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.85rem; font-weight: 500;">Department</label>
        <select id="filterDept" class="form-control" style="width: 100%; padding: 0.6rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            <option value="all">All Departments</option>
            <?php foreach($departments as $d): ?>
                <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="calendar-filter-field">
        <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.85rem; font-weight: 500;">Employee</label>
        <select id="filterEmp" class="form-control" style="width: 100%; padding: 0.6rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            <option value="all">All Employees</option>
            <?php foreach($employees as $e): ?>
                <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['first_name'] . ' ' . $e['last_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="calendar-filter-action">
        <button id="applyFiltersBtn" class="btn btn-primary" style="padding: 0.6rem 1.5rem;"><i class="fas fa-filter"></i> Filter</button>
    </div>
</div>

<div class="card calendar-card">
    <!-- Calendar Container -->
    <div id="calendar"></div>
</div>

<!-- Event Detail Modal -->
<div id="eventModal" class="calendar-modal">
    <div class="calendar-modal-panel">
        <div id="modalHeader" style="padding: 1.5rem; display: flex; align-items: center; border-bottom: 1px solid var(--border-color);">
            <div id="modalIcon" style="margin-right: 1rem; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;"></div>
            <div style="flex: 1;">
                <h3 id="modalTitle" style="margin: 0; color: var(--text-primary); font-size: 1.2rem; font-weight: 600;">Event Title</h3>
                <span id="modalTypeBadge" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.2rem 0.5rem; border-radius: 4px; margin-top: 0.4rem; display: inline-block;">TYPE</span>
            </div>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer; padding: 0.5rem;"><i class="fas fa-times"></i></button>
        </div>
        <div style="padding: 1.5rem;">
            <div style="margin-bottom: 1.2rem; display: flex; align-items: center; color: var(--text-secondary); font-size: 0.95rem;">
                <i class="far fa-clock" style="margin-right: 0.8rem; font-size: 1.1rem; color: var(--accent-primary);"></i>
                <span id="modalDate">Date Range</span>
            </div>
            
            <div id="modalDetails" style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 0.95rem; line-height: 1.5;">
                <!-- Dynamically populated details -->
            </div>
        </div>
        <div style="padding: 1rem 1.5rem; background: var(--bg-secondary); text-align: right; border-top: 1px solid var(--border-color);">
            <button onclick="closeModal()" class="btn" style="background: var(--bg-hover); color: var(--text-primary); font-weight: 500;">Close</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var isMobile = window.matchMedia('(max-width: 720px)').matches;

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        contentHeight: 'auto',
        dayMaxEvents: isMobile ? 2 : 3,
        moreLinkClick: 'popover',
        headerToolbar: isMobile ? {
            left: 'prev,next',
            center: 'title',
            right: 'today,dayGridMonth,listWeek'
        } : {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        buttonText: {
            today: 'Today',
            month: 'Month',
            week: 'Week',
            day: 'Day',
            list: 'List'
        },
        themeSystem: 'standard',
        events: function(info, successCallback, failureCallback) {
            const type = document.getElementById('filterType').value;
            const dept = document.getElementById('filterDept').value;
            const emp = document.getElementById('filterEmp').value;
            
            let url = `ajax_events.php?type=${type}&department=${encodeURIComponent(dept)}`;
            if(emp !== 'all') {
                url += `&employee_id=${emp}`;
            }

            fetch(url)
                .then(response => response.json())
                .then(data => successCallback(data))
                .catch(error => failureCallback(error));
        },
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short'
        },
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            showEventDetails(info.event);
        },
        eventDidMount: function(info) {
            info.el.style.borderRadius = '4px';
            info.el.style.padding = '2px 4px';
            info.el.style.borderWidth = '1px';
            info.el.style.borderStyle = 'solid';
            info.el.style.cursor = 'pointer';
            info.el.style.marginBottom = '2px';
            info.el.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';
        },
        windowResize: function() {
            const shouldBeMobile = window.matchMedia('(max-width: 720px)').matches;
            calendar.setOption('headerToolbar', shouldBeMobile ? {
                left: 'prev,next',
                center: 'title',
                right: 'today,dayGridMonth,listWeek'
            } : {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            });
            calendar.setOption('dayMaxEvents', shouldBeMobile ? 2 : 3);
        }
    });

    calendar.render();

    document.getElementById('applyFiltersBtn').addEventListener('click', function() {
        calendar.refetchEvents();
    });

    setTimeout(() => {
        const fcBtns = document.querySelectorAll('.fc-button');
        fcBtns.forEach(btn => {
            btn.style.background = 'var(--bg-secondary)';
            btn.style.color = 'var(--text-primary)';
            btn.style.borderColor = 'var(--border-color)';
            btn.style.boxShadow = 'none';
            btn.style.textTransform = 'capitalize';
            btn.style.fontWeight = '500';
            
            btn.addEventListener('mouseenter', () => btn.style.background = 'var(--bg-hover)');
            btn.addEventListener('mouseleave', () => {
                if(!btn.classList.contains('fc-button-active')) {
                    btn.style.background = 'var(--bg-secondary)';
                }
            });
        });

        const activeBtns = document.querySelectorAll('.fc-button-active');
        activeBtns.forEach(btn => {
            btn.style.background = 'var(--accent-primary)';
            btn.style.color = '#ffffff';
            btn.style.borderColor = 'var(--accent-primary)';
        });
    }, 100);
});

function showEventDetails(event) {
    const modal = document.getElementById('eventModal');
    const header = document.getElementById('modalHeader');
    const icon = document.getElementById('modalIcon');
    const title = document.getElementById('modalTitle');
    const badge = document.getElementById('modalTypeBadge');
    const dateStr = document.getElementById('modalDate');
    const details = document.getElementById('modalDetails');
    
    const props = event.extendedProps;
    
    if (props.type === 'Holiday') {
        icon.style.background = 'rgba(239, 68, 68, 0.1)';
        icon.style.color = 'var(--accent-danger)';
        badge.style.background = 'rgba(239, 68, 68, 0.1)';
        badge.style.color = 'var(--accent-danger)';
    } else if (props.type === 'Leave') {
        icon.style.background = 'rgba(16, 185, 129, 0.1)';
        icon.style.color = 'var(--accent-success)';
        badge.style.background = 'rgba(16, 185, 129, 0.1)';
        badge.style.color = 'var(--accent-success)';
    } else {
        icon.style.background = 'rgba(59, 130, 246, 0.1)';
        icon.style.color = 'var(--accent-primary)';
        badge.style.background = 'rgba(59, 130, 246, 0.1)';
        badge.style.color = 'var(--accent-primary)';
    }

    icon.innerHTML = `<i class="fas ${props.icon}"></i>`;
    title.textContent = event.title;
    badge.textContent = props.type;

    let dStr = '';
    const sDate = event.start.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    
    if (event.allDay && (!event.end || event.end.getTime() === event.start.getTime() + 86400000)) {
        dStr = sDate + ' (All Day)';
    } else if (event.allDay && event.end) {
        const eDate = new Date(event.end.getTime() - 86400000).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        dStr = sDate === eDate ? sDate : sDate + ' - ' + eDate;
    } else {
        const sTime = event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        const eTime = event.end ? event.end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
        dStr = sDate + (eTime ? ` (${sTime} - ${eTime})` : ` (${sTime})`);
    }
    dateStr.textContent = dStr;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function(char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function renderLocation(value) {
        const location = String(value || '').trim();
        if (!location) return 'N/A';

        if (/^https?:\/\//i.test(location)) {
            return `<a href="${escapeHtml(location)}" target="_blank" rel="noopener" style="color: var(--accent-primary);">${escapeHtml(location)}</a>`;
        }

        return escapeHtml(location);
    }

    let dHtml = '';
    if (props.type === 'Holiday') {
        dHtml = `
            <div style="margin-bottom: 0.8rem;"><strong style="color: var(--text-secondary);">Category:</strong> ${props.category}</div>
            <div style="margin-bottom: 0.8rem;"><strong style="color: var(--text-secondary);">Branches:</strong> ${props.applies_to_all_branches}</div>
            <div style="margin-bottom: 0.8rem;"><strong style="color: var(--text-secondary);">Type:</strong> ${props.is_paid} Holiday</div>
            <div style="margin-top: 1rem;"><strong style="color: var(--text-secondary);">Description:</strong><br>${props.description || 'No description.'}</div>
        `;
    } else if (props.type === 'Leave') {
        dHtml = `
            <div style="margin-bottom: 0.8rem;"><strong style="color: var(--text-secondary);">Employee:</strong> ${props.employee}</div>
            <div style="margin-bottom: 0.8rem;"><strong style="color: var(--text-secondary);">Department:</strong> ${props.department || 'N/A'}</div>
            <div style="margin-bottom: 0.8rem;"><strong style="color: var(--text-secondary);">Leave Type:</strong> ${props.leave_type} (${props.total_days} days)</div>
            <div style="margin-bottom: 0.8rem;"><strong style="color: var(--text-secondary);">Approved On:</strong> ${props.approval_date}</div>
            <div style="margin-top: 1rem;"><strong style="color: var(--text-secondary);">Reason:</strong><br>${props.reason || 'No reason provided.'}</div>
        `;
    } else if (props.type === 'Meeting') {
        dHtml = `
            <div style="margin-bottom: 0.8rem;"><strong style="color: var(--text-secondary);">Organizer:</strong> ${props.organizer}</div>
            <div style="margin-bottom: 0.8rem;"><strong style="color: var(--text-secondary);">Location/Link:</strong> ${renderLocation(props.location)}</div>
            <div style="margin-bottom: 0.8rem;"><strong style="color: var(--text-secondary);">Status:</strong> ${props.status}</div>
            <div style="margin-top: 1rem;"><strong style="color: var(--text-secondary);">Agenda/Description:</strong><br>${props.description || 'No description provided.'}</div>
        `;
    }
    details.innerHTML = dHtml;

    modal.style.display = 'flex';
    setTimeout(() => {
        modal.firstElementChild.style.transform = 'scale(1)';
    }, 10);
}

function closeModal() {
    const modal = document.getElementById('eventModal');
    modal.firstElementChild.style.transform = 'scale(0.95)';
    setTimeout(() => {
        modal.style.display = 'none';
    }, 200);
}

document.getElementById('eventModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<style>
.calendar-filter-card {
    margin-bottom: 1.5rem;
    padding: 1rem 1.5rem;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: flex-end;
    background: var(--bg-main);
}
.calendar-filter-field {
    flex: 1 1 200px;
    min-width: 0;
}
.calendar-filter-action {
    flex: 0 0 auto;
}
.calendar-card {
    padding: 1.5rem;
    overflow: hidden;
}
.calendar-modal {
    display: none;
    position: fixed;
    inset: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    backdrop-filter: blur(4px);
    padding: 1rem;
}
.calendar-modal-panel {
    background: var(--bg-main);
    width: min(100%, 500px);
    max-height: calc(100vh - 2rem);
    border-radius: var(--radius-lg);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    overflow: auto;
    transform: scale(0.95);
    transition: transform 0.2s;
}
#calendar {
    max-width: 100%;
}
.fc-theme-standard td, .fc-theme-standard th, .fc-theme-standard .fc-scrollgrid {
    border-color: var(--border-color) !important;
}
.fc .fc-toolbar {
    gap: 0.75rem;
}
.fc .fc-toolbar-title {
    font-size: 1.25rem;
    line-height: 1.25;
}
.fc .fc-button {
    min-height: 36px;
}
.fc-col-header-cell {
    background: var(--bg-secondary);
    padding: 0.75rem 0 !important;
    font-weight: 600 !important;
    color: var(--text-secondary);
}
.fc-daygrid-day-number {
    color: var(--text-secondary);
    font-weight: 500;
    padding: 0.5rem !important;
    text-decoration: none !important;
}
.fc-daygrid-day-number:hover {
    text-decoration: none !important;
}
.fc-day-today {
    background: rgba(37, 99, 235, 0.02) !important;
}
.fc-day-today .fc-daygrid-day-number {
    color: var(--accent-primary);
    background: rgba(37, 99, 235, 0.1);
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 4px;
}
.fc-event-title {
    font-weight: 500 !important;
}
.fc-event-title,
.fc-event-time {
    white-space: normal;
}
.fc-list-event-title a,
.fc-list-event-time {
    color: var(--text-primary) !important;
}
@media (max-width: 900px) {
    .calendar-filter-card {
        padding: 1rem;
    }
    .calendar-card {
        padding: 1rem;
    }
    .fc .fc-toolbar {
        align-items: stretch;
        flex-direction: column;
    }
    .fc .fc-toolbar-chunk {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.35rem;
    }
    .fc .fc-toolbar-title {
        text-align: center;
    }
}
@media (max-width: 720px) {
    .calendar-filter-card {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.85rem;
    }
    .calendar-filter-action,
    .calendar-filter-action .btn {
        width: 100%;
    }
    .calendar-filter-action .btn {
        justify-content: center;
    }
    .fc .fc-toolbar-title {
        font-size: 1.05rem;
    }
    .fc .fc-button {
        padding: 0.38rem 0.55rem;
        font-size: 0.78rem;
    }
    .fc .fc-list-event-title,
    .fc .fc-list-event-time {
        font-size: 0.82rem;
    }
    .fc .fc-list-day-cushion {
        padding: 0.55rem 0.7rem;
    }
    .fc .fc-daygrid-day-frame {
        min-height: 58px;
    }
    .fc .fc-daygrid-day-number {
        font-size: 0.78rem;
        padding: 0.35rem !important;
    }
    .fc .fc-daygrid-event {
        font-size: 0.68rem;
        line-height: 1.2;
        padding: 1px 3px !important;
    }
    .fc .fc-daygrid-more-link {
        font-size: 0.68rem;
    }
    .calendar-modal {
        align-items: flex-end;
        padding: 0.75rem;
    }
    .calendar-modal-panel {
        width: 100%;
        max-height: 88vh;
        border-radius: var(--radius-lg) var(--radius-lg) var(--radius-sm) var(--radius-sm);
    }
    #modalHeader {
        padding: 1rem !important;
        align-items: flex-start !important;
    }
    #modalIcon {
        width: 34px !important;
        height: 34px !important;
        margin-right: 0.75rem !important;
        flex-shrink: 0;
    }
    #modalTitle {
        font-size: 1rem !important;
        overflow-wrap: anywhere;
    }
    #modalDetails {
        font-size: 0.86rem !important;
        overflow-wrap: anywhere;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>
