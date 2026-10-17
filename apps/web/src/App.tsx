import { Route, Routes } from 'react-router-dom'
import { AppShell } from '@/components/layout/AppShell'
import { RequireAuth } from '@/components/RequireAuth'
import { ActivityPage } from '@/pages/ActivityPage'
import { LibraryPage } from '@/pages/LibraryPage'
import { ToolPage } from '@/pages/ToolPage'
import { ToolsPage } from '@/pages/ToolsPage'
import { DashboardPage } from '@/pages/DashboardPage'
import { LegalPage } from '@/pages/LegalPage'
import { QuickStartPage } from '@/pages/QuickStartPage'
import { SkillsPage } from '@/pages/SkillsPage'
import { LoginPage } from '@/pages/LoginPage'
import { NotFoundPage } from '@/pages/errors/NotFoundPage'
import { ForbiddenPage } from '@/pages/errors/ForbiddenPage'
import { ServerErrorPage } from '@/pages/errors/ServerErrorPage'
import { ServiceUnavailablePage } from '@/pages/errors/ServiceUnavailablePage'
import { SessionExpiredPage } from '@/pages/errors/SessionExpiredPage'
import { TooManyRequestsPage } from '@/pages/errors/TooManyRequestsPage'
import { UnauthorizedPage } from '@/pages/errors/UnauthorizedPage'
import { ProjectDetailPage } from '@/pages/ProjectDetailPage'
import { ProjectsPage } from '@/pages/ProjectsPage'
import { RegisterPage } from '@/pages/RegisterPage'
import { PricingPage } from '@/pages/PricingPage'
import { BillingPage } from '@/pages/BillingPage'
import { ClipsPage } from '@/pages/ClipsPage'
import { ProductionPage } from '@/pages/ProductionPage'
import { SecureGatePage } from '@/pages/admin/SecureGatePage'
import { AdminOverviewPage } from '@/pages/admin/AdminOverviewPage'
import { AdminUsersPage } from '@/pages/admin/AdminUsersPage'
import { AdminUserPage } from '@/pages/admin/AdminUserPage'
import { AdminPricingPage } from '@/pages/admin/AdminPricingPage'
import { AdminAuditPage } from '@/pages/admin/AdminAuditPage'
import { AdminProvidersPage } from '@/pages/admin/AdminProvidersPage'
import { AdminApiKeysPage } from '@/pages/admin/AdminApiKeysPage'
import { AdminEmailPage } from '@/pages/admin/AdminEmailPage'
import { AdminSystemPage } from '@/pages/admin/AdminSystemPage'
import { AdminBillingPage } from '@/pages/admin/AdminBillingPage'
import { AdminTeamPage } from '@/pages/admin/AdminTeamPage'
import { AdminPaymentsPage } from '@/pages/admin/AdminPaymentsPage'

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/pricing" element={<PricingPage />} />
      <Route path="/privacy" element={<LegalPage doc="privacy" />} />
      <Route path="/terms" element={<LegalPage doc="terms" />} />
      <Route path="/acceptable-use" element={<LegalPage doc="acceptable-use" />} />
      <Route path="/cookies" element={<LegalPage doc="cookies" />} />
      <Route path="/401" element={<UnauthorizedPage />} />
      <Route path="/403" element={<ForbiddenPage />} />
      <Route path="/419" element={<SessionExpiredPage />} />
      <Route path="/429" element={<TooManyRequestsPage />} />
      <Route path="/500" element={<ServerErrorPage />} />
      <Route path="/503" element={<ServiceUnavailablePage />} />

      <Route element={<RequireAuth />}>
        <Route element={<AppShell />}>
          <Route index element={<DashboardPage />} />
          <Route path="skills" element={<SkillsPage />} />
          <Route path="quick-start/:key" element={<QuickStartPage />} />
          <Route path="projects" element={<ProjectsPage />} />
          <Route path="activity" element={<ActivityPage />} />
          <Route path="billing" element={<BillingPage />} />
          <Route path="clips" element={<ClipsPage />} />
          <Route path="productions/:id" element={<ProductionPage />} />
          <Route path="tools" element={<ToolsPage />} />
          <Route path="tools/:tool" element={<ToolPage />} />
          <Route path="library" element={<LibraryPage />} />
          <Route path="projects/:id" element={<ProjectDetailPage />} />
        </Route>
      </Route>

      {/* The owner console. Hidden from the studio navigation; only super admins see its pages. */}
      <Route path="/securegate" element={<SecureGatePage />}>
        <Route index element={<AdminOverviewPage />} />
        <Route path="billing" element={<AdminBillingPage />} />
        <Route path="team" element={<AdminTeamPage />} />
        <Route path="users" element={<AdminUsersPage />} />
        <Route path="users/:id" element={<AdminUserPage />} />
        <Route path="providers" element={<AdminProvidersPage />} />
        <Route path="keys" element={<AdminApiKeysPage />} />
        <Route path="payments" element={<AdminPaymentsPage />} />
        <Route path="pricing" element={<AdminPricingPage />} />
        <Route path="email" element={<AdminEmailPage />} />
        <Route path="system" element={<AdminSystemPage />} />
        <Route path="audit" element={<AdminAuditPage />} />
      </Route>

      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  )
}
