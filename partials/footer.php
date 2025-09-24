  </main>
  <!-- 메인 컨테이너 끝 -->

  <footer class="bg-white border-t">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-sm text-gray-600 flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between">
      <p>© <?= htmlspecialchars(__('footer.copy'), ENT_QUOTES, 'UTF-8') ?></p>
      <div class="flex gap-4">
        <a href="#" class="hover:text-primary"><?= htmlspecialchars(__('footer.terms'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="#" class="hover:text-primary"><?= htmlspecialchars(__('footer.privacy'), ENT_QUOTES, 'UTF-8') ?></a>
      </div>
    </div>
  </footer>
</body>
</html>