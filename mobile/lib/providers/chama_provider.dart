import 'package:flutter/foundation.dart';

import '../core/api_exception.dart';
import '../core/models/chama.dart';
import '../repositories/chama_repository.dart';

class ChamaProvider extends ChangeNotifier {
  ChamaProvider({ChamaRepository? repository}) : _repository = repository ?? ChamaRepository();

  final ChamaRepository _repository;

  List<Chama> chamas = [];
  bool isLoading = false;
  String? error;

  Future<void> load() async {
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      chamas = await _repository.myChamas();
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }
}
