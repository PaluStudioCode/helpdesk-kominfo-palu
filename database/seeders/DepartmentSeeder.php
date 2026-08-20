<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            ['code' => 'SETDA-PALU', 'name' => 'Sekretariat Daerah Kota Palu'],
            ['code' => 'R-WALIKOTA', 'name' => 'Ruang Walikota'],
            ['code' => 'R-WAKIL-WALIKOTA', 'name' => 'Ruang Wakil Walikota'],
            ['code' => 'R-SEKRETARIS-KOTA', 'name' => 'Ruang Sekretaris Kota'],
            ['code' => 'R-ASISTEN-1', 'name' => 'Ruang Assisten 1'],
            ['code' => 'R-ASISTEN-2', 'name' => 'Ruang Assisten 2'],
            ['code' => 'R-ASISTEN-3', 'name' => 'Ruang Assisten 3'],
            ['code' => 'R-STAF-AHLI', 'name' => 'Ruang Staf Ahli'],
            ['code' => 'BAG-KEUANGAN', 'name' => 'Bagian Keuangan'],
            ['code' => 'BAG-ORGANISASI', 'name' => 'Bagian Organisasi'],
            ['code' => 'BAG-PEMBANGUNAN', 'name' => 'Bagian Pembangunan'],
            ['code' => 'BAG-HUKUM', 'name' => 'Bagian Hukum'],
            ['code' => 'BAG-PEMERINTAHAN', 'name' => 'Bagian Pemerintahan'],
            ['code' => 'BAG-PEREKONOMIAN', 'name' => 'Bagian Perekonomian'],
            ['code' => 'BAG-UMUM', 'name' => 'Bagian Umum'],
            ['code' => 'BAG-KESRA', 'name' => 'Bagian Kesejahteraan Rakyat'],
            ['code' => 'BAG-PROKOPIM', 'name' => 'Bagian Protokol dan Komunikasi Pimpinan'],
            ['code' => 'BAG-PBJ', 'name' => 'Bagian Pengadaan Barang dan Jasa'],
            ['code' => 'R-BANTAYA', 'name' => 'Ruang Pertemuan Bantaya'],
            ['code' => 'R-KASIROMU', 'name' => 'Ruang Pertemuan Kasiromu'],
            ['code' => 'SETWAN-PALU', 'name' => 'Sekretariat DPRD Kota Palu'],
            ['code' => 'DPMPTSP-PALU', 'name' => 'Dinas Penanaman Modal dan Pelayanan Terpadu Satu Pintu Kota Palu'],
            ['code' => 'BKPSDM-PALU', 'name' => 'Badan Kepegawaian dan Pengembangan Sumber Daya Manusia Kota Palu'],
            ['code' => 'DISDUKCAPIL-PALU', 'name' => 'Dinas Kependudukan dan Pencatatan Sipil Kota Palu'],
            ['code' => 'DPRP-PALU', 'name' => 'Dinas Penataan Ruang dan Pertahanan Kota Palu'],
            ['code' => 'BAPPEDA-PALU', 'name' => 'Badan Perencanaan Pembangunan Daerah (Bappeda)'],
            ['code' => 'INSPEKTORAT-PALU', 'name' => 'Inspektorat Daerah Kota Palu'],
            ['code' => 'DINKES', 'name' => 'Dinas Kesehatan Kota Palu'],
            ['code' => 'PKM-SINGGANI', 'name' => 'Puskesmas Singgani'],
            ['code' => 'PKM-NOSARARA', 'name' => 'Puskesmas Nosarara'],
            ['code' => 'PKM-SANGURARA', 'name' => 'Puskesmas Sangurara'],
            ['code' => 'PKM-PANTOLOAN', 'name' => 'Puskesmas Pantoloan'],
            ['code' => 'PKM-MABELOPURA', 'name' => 'Puskesmas Mabelopura'],
            ['code' => 'PKM-BIROBULI', 'name' => 'Puskesmas Birobuli'],
            ['code' => 'PKM-LERE', 'name' => 'Puskesmas Lere'],
            ['code' => 'PKM-MAMBORO', 'name' => 'Puskesmas Mamboro'],
            ['code' => 'PKM-TAWAELI', 'name' => 'Puskesmas Tawaeli'],
            ['code' => 'PKM-BULILI', 'name' => 'Puskesmas Bulili'],
            ['code' => 'PKM-TALISE', 'name' => 'Puskesmas Talise'],
            ['code' => 'PKM-KAMONJI', 'name' => 'Puskesmas Kamonji'],
            ['code' => 'PKM-TIPO', 'name' => 'Puskesmas Tipo'],
            ['code' => 'PKM-KAWATUNA', 'name' => 'Puskesmas Kawatuna'],
            ['code' => 'DISDIK', 'name' => 'Dinas Pendidikan dan Kebudayaan Kota Palu'],
            ['code' => 'BPKAD-PALU', 'name' => 'Badan Pengelolaan Keuangan Dan Aset Daerah Kota Palu'],
            ['code' => 'BAPENDA-PALU', 'name' => 'Badan Pendapatan Daerah Kota Palu'],
            ['code' => 'DISPORA-PALU', 'name' => 'Dinas Pemuda Dan Olahraga Kota Palu'],
            ['code' => 'DAMKAR-PALU', 'name' => 'Dinas Pemadam Kebakaran Dan Penyelamatan Kota Palu'],
            ['code' => 'DISKOMINFO-PALU', 'name' => 'Dinas Komunikasi Informatika Persandian dan Statistik Kota Palu'],
            ['code' => 'DISHUB-PALU', 'name' => 'Dinas Perhubungan Kota Palu'],
            ['code' => 'BPBD-PALU', 'name' => 'Badan Penanggulangan Bencana Daerah Kota Palu'],
            ['code' => 'SATPOLPP-PALU', 'name' => 'Satuan Polisi Pamong Praja Kota Palu'],
            ['code' => 'DISPERKIM-PALU', 'name' => 'Dinas Perumahan dan Kawasan Permukiman Kota Palu'],
            ['code' => 'DLH-PALU', 'name' => 'Dinas Lingkungan Hidup'],
            ['code' => 'DP3A-PALU', 'name' => 'Dinas Pemberdayaan Perempuan dan Perlindungan Anak Kota Palu'],
            ['code' => 'DPPKB-PALU', 'name' => 'Dinas Pengendalian Penduduk dan Keluarga Berencana Kota Palu'],
            ['code' => 'KEK-PALU', 'name' => 'Kawasan Ekonomi Khusus (Kek) Kota Palu'],
            ['code' => 'BALITBANGDA-PALU', 'name' => 'Badan Penelitian dan Pengembangan Daerah Kota Palu'],
            ['code' => 'DPKP-PALU', 'name' => 'Dinas Pertanian dan Ketahanan Pangan Kota Palu'],
            ['code' => 'DISPAR-PALU', 'name' => 'Dinas Pariwisata Kota Palu'],
            ['code' => 'BAKESBANGPOL-PALU', 'name' => 'Badan Kesatuan Bangsa Dan Politik Kota Palu'],
            ['code' => 'DPU-PALU', 'name' => 'Dinas Pekerjaan Umum Kota Palu'],
            ['code' => 'DISPERINDAG-PALU', 'name' => 'Dinas Perdagangan Dan Perindustrian Kota Palu'],
            ['code' => 'DISARPUS-PALU', 'name' => 'Dinas Kearsipan Dan Perpustakaan Kota Palu'],
            ['code' => 'DISKUKMNKER-PALU', 'name' => 'Dinas Koperasi, Usaha Kecil dan Menengah, dan Tenaga Kerja Kota Palu'],
            ['code' => 'DINSOS-PALU', 'name' => 'Dinas Sosial Kota Palu'],
            ['code' => 'RSU-ANUTAPURA', 'name' => 'Rumah Sakit Umum Anatapura'],
            ['code' => 'KEC-TAWAELI', 'name' => 'Kecamatan Tawaeli'],
            ['code' => 'KEC-ULUJADI', 'name' => 'Kecamatan Ulujadi'],
            ['code' => 'KEC-PALU-BARAT', 'name' => 'Kecamatan Palu Barat'],
            ['code' => 'KEC-PALU-SELATAN', 'name' => 'Kecamatan Palu Selatan'],
            ['code' => 'KEC-PALU-UTARA', 'name' => 'Kecamatan Palu Utara'],
            ['code' => 'KEC-PALU-TIMUR', 'name' => 'Kecamatan Palu Timur'],
            ['code' => 'KEC-MANTIKULORE', 'name' => 'Kecamatan Mantikulore'],
            ['code' => 'KEC-TATANGA', 'name' => 'Kecamatan Tatanga'],
            ['code' => 'KEL-KAYUMALUE-NGAPA', 'name' => 'Kelurahan Kayumalue Ngapa'],
            ['code' => 'KEL-KAYUMALUE-PAJEKO', 'name' => 'Kelurahan Kayumalue Pajeko'],
            ['code' => 'KEL-TAIPA', 'name' => 'Kelurahan Taipa'],
            ['code' => 'KEL-MAMBORO', 'name' => 'Kelurahan Mamboro'],
            ['code' => 'KEL-MAMBORO-BARAT', 'name' => 'Kelurahan Mamboro Barat'],
            ['code' => 'KEL-PANTOLOAN', 'name' => 'Kelurahan Pantoloan'],
            ['code' => 'KEL-PANTOLOAN-BOYA', 'name' => 'Kelurahan Pantoloan Boya'],
            ['code' => 'KEL-BAIYA', 'name' => 'Kelurahan Baiya'],
            ['code' => 'KEL-LAMBARA', 'name' => 'Kelurahan Lambara'],
            ['code' => 'KEL-PANAU', 'name' => 'Kelurahan Panau'],
            ['code' => 'KEL-SILAE', 'name' => 'Kelurahan Silae'],
            ['code' => 'KEL-KABONENA', 'name' => 'Kelurahan Kabonena'],
            ['code' => 'KEL-DONGGALA-KODI', 'name' => 'Kelurahan Donggala Kodi'],
            ['code' => 'KEL-WATUSAMPU', 'name' => 'Kelurahan Watusampu'],
            ['code' => 'KEL-BULURI', 'name' => 'Kelurahan Buluri'],
            ['code' => 'KEL-TIPO', 'name' => 'Kelurahan Tipo'],
            ['code' => 'KEL-BARU', 'name' => 'Kelurahan Baru'],
            ['code' => 'KEL-SIRANINDI', 'name' => 'Kelurahan Siranindi'],
            ['code' => 'KEL-KAMONJI', 'name' => 'Kelurahan Kamonji'],
            ['code' => 'KEL-BALAROA', 'name' => 'Kelurahan Balaroa'],
            ['code' => 'KEL-LERE', 'name' => 'Kelurahan Lere'],
            ['code' => 'KEL-UJUNA', 'name' => 'Kelurahan Ujuna'],
            ['code' => 'KEL-PETOBO', 'name' => 'Kelurahan Petobo'],
            ['code' => 'KEL-TATURA-UTARA', 'name' => 'Kelurahan Tatura Utara'],
            ['code' => 'KEL-TATURA-SELATAN', 'name' => 'Kelurahan Tatura Selatan'],
            ['code' => 'KEL-BIROBULI-SELATAN', 'name' => 'Kelurahan Birobuli Selatan'],
            ['code' => 'KEL-BIROBULI-UTARA', 'name' => 'Kelurahan Birobuli Utara'],
        ];

        $defaultPassword = Hash::make('password');

        foreach ($departments as $index => $dept) {
            $department = Department::updateOrCreate(
                ['code' => $dept['code']],
                [
                    'name' => $dept['name'],
                    'address' => 'Kota Palu',
                    'status' => 'active',
                ]
            );

            // Format slug code untuk email prefix
            $slugCode = strtolower(str_replace('-', '.', $dept['code']));
            $email = $dept['code'] === 'DINKES' 
                ? 'operator@dinkes.palukota.go.id' 
                : ($dept['code'] === 'DISDIK' ? 'operator@disdik.palukota.go.id' : "operator.{$slugCode}@palukota.go.id");
            
            $phoneSuffix = str_pad((string)($index + 1), 4, '0', STR_PAD_LEFT);
            $phoneNumber = "08123456{$phoneSuffix}";

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => "Operator {$dept['name']}",
                    'password' => $defaultPassword,
                    'department_id' => $department->id,
                    'role' => 'opd_user',
                    'phone_number' => $phoneNumber,
                    'status' => 'active',
                ]
            );
        }
    }
}
