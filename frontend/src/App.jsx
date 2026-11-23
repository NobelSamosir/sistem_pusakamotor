import { Navigate, Route, Routes } from 'react-router-dom'
import { useAuth } from './lib/auth'
import Layout from './components/Layout'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import Motors from './pages/Motors'
import MotorDetail from './pages/MotorDetail'
import Purchases from './pages/Purchases'
import Costs from './pages/Costs'
import Sales from './pages/Sales'
import Expenses from './pages/Expenses'
import Predict from './pages/Predict'
import Recommend from './pages/Recommend'
import Reports from './pages/Reports'
import Journals from './pages/Journals'
import Master from './pages/Master'
import Deposits from './pages/Deposits'
import Consignments from './pages/Consignments'
import Leasing from './pages/Leasing'
import Supplies from './pages/Supplies'
import DocumentServices from './pages/DocumentServices'

function Protected({ children }) {
  const { user } = useAuth()
  return user ? children : <Navigate to="/login" replace />
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route element={<Protected><Layout /></Protected>}>
        <Route index element={<Dashboard />} />
        <Route path="motor" element={<Motors />} />
        <Route path="motor/:id" element={<MotorDetail />} />
        <Route path="pembelian" element={<Purchases />} />
        <Route path="biaya" element={<Costs />} />
        <Route path="penjualan" element={<Sales />} />
        <Route path="beban" element={<Expenses />} />
        <Route path="prediksi" element={<Predict />} />
        <Route path="rekomendasi" element={<Recommend />} />
        <Route path="laporan" element={<Reports />} />
        <Route path="jurnal" element={<Journals />} />
        <Route path="master" element={<Master />} />
        <Route path="uang-muka" element={<Deposits />} />
        <Route path="titip-jual" element={<Consignments />} />
        <Route path="leasing" element={<Leasing />} />
        <Route path="perlengkapan" element={<Supplies />} />
        <Route path="jasa-surat" element={<DocumentServices />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
