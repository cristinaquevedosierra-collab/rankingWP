# -*- coding: utf-8 -*-
from pathlib import Path
b = Path('includes/template-parts/player-hitos-tab.php').read_bytes()
print(list(b[:120]))
