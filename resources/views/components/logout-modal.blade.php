<div class="modal fade sipuri-logout-modal" id="logout-confirmation" tabindex="-1" aria-labelledby="logout-confirmation-title" aria-describedby="logout-confirmation-description" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h2 class="modal-title fs-5" id="logout-confirmation-title">Keluar dari SIPURI?</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
  <div class="modal-body" id="logout-confirmation-description">Anda yakin ingin keluar dari akun ini?</div>
  <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Batal</button><form action="{{ route('logout') }}" method="post" data-submit-form>@csrf<button class="btn btn-primary" type="submit" data-busy-label="Keluar…">Ya, Keluar</button></form></div>
 </div></div>
</div>
