const fs = require('fs');

const files = fs.readdirSync('.').filter(f => f.startsWith('admin-') && f.endsWith('.html'));

const sidebarNavLinksPattern = /<div class="nav-links">[\s\S]*?<\/div>/;

files.forEach(f => {
    let content = fs.readFileSync(f, 'utf8');
    
    // We want to replace everything inside nav-links and recreate it.
    let activeDashboard = f === 'admin-dashboard.html' ? ' active' : '';
    let activeApprovals = f === 'admin-approvals.html' ? ' active' : '';
    let activePharmacies = f === 'admin-pharmacies.html' ? ' active' : '';
    let activePatients = f === 'admin-patients.html' ? ' active' : '';
    let activeCommissions = f === 'admin-commissions.html' ? ' active' : '';
    let activeInvoices = f === 'admin-invoices.html' ? ' active' : '';
    let activeSettings = f === 'admin-settings.html' ? ' active' : '';
    
    let replacement = `<div class="nav-links">
      <a href="admin-dashboard.html" class="nav-link${activeDashboard}"><i class="fas fa-chart-pie"></i> Dashboard</a>
      <a href="admin-approvals.html" class="nav-link${activeApprovals}"><i class="fas fa-file-signature"></i> Approvals <span class="badge" id="sidebarPendingBadge" style="display: none; background: #E53E3E; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; margin-left: auto; font-weight: bold;">0</span></a>
      <a href="admin-pharmacies.html" class="nav-link${activePharmacies}"><i class="fas fa-store"></i> Pharmacies</a>
      <a href="admin-patients.html" class="nav-link${activePatients}"><i class="fas fa-users"></i> Patients</a>
      <a href="admin-commissions.html" class="nav-link${activeCommissions}"><i class="fas fa-hand-holding-usd"></i> Commissions</a>
      <a href="admin-invoices.html" class="nav-link${activeInvoices}"><i class="fas fa-file-invoice-dollar"></i> Invoices</a>
      <a href="admin-settings.html" class="nav-link${activeSettings}"><i class="fas fa-cog"></i> Settings</a>
    </div>`;

    if (sidebarNavLinksPattern.test(content)) {
        content = content.replace(sidebarNavLinksPattern, replacement);
        fs.writeFileSync(f, content);
    }
});
console.log('Done replacing admin sidebars');
